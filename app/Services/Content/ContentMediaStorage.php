<?php

namespace App\Services\Content;

use App\Enums\MediaPurpose;
use App\Enums\PlanMetric;
use App\Http\Middleware\ScopedAdminContent;
use App\Models\ContentMedia;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Platform\AdminContentMutation;
use App\Services\Platform\PlatformAuthorization;
use App\Services\Settings\PlatformSettings;
use ErrorException;
use finfo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class ContentMediaStorage
{
    public function __construct(private readonly PlatformSettings $settings) {}

    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    private const MAX_FILE_BYTES = 25 * 1024 * 1024;

    private const MAX_IMAGE_PIXELS = 20_000_000;

    /** @var array<string, list<string>> */
    private const IMAGE_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];

    public function store(UploadedFile $file, Workspace $workspace, User $user, MediaPurpose $purpose): ContentMedia
    {
        $scope = request()->attributes->get(ScopedAdminContent::ATTRIBUTE);
        $scopedAdmin = is_array($scope)
            && request()->user()?->is($user)
            && (int) $scope['actor_id'] === (int) $user->getKey()
            && $scope['workspace']->is($workspace)
            && app(PlatformAuthorization::class)->allows($user, $scope['permission']);
        if (! $scopedAdmin && ! $user->belongsToWorkspace($workspace)) {
            throw new AuthorizationException('The user is not a member of the media workspace.');
        }

        if (! $file->isValid() || ! is_string($file->getRealPath())) {
            $this->rejectFile();
        }

        $size = $file->getSize();
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! is_int($size) || $size < 1 || ! is_string($mimeType)) {
            $this->rejectFile();
        }

        $originalExtension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $scanState = 'not_required';
        $temporaryPath = null;

        try {
            if ($purpose === MediaPurpose::Image || $purpose === MediaPurpose::AccountAvatar) {
                $maxImageBytes = $this->softLimitBytes('max_image_mb', self::MAX_IMAGE_BYTES);
                if (
                    $size > $maxImageBytes
                    || ! isset(self::IMAGE_EXTENSIONS[$mimeType])
                    || ! in_array($originalExtension, self::IMAGE_EXTENSIONS[$mimeType], true)
                ) {
                    $this->rejectFile();
                }

                $temporaryPath = $this->reencodeImage($file->getRealPath(), $mimeType);
                $sourcePath = $temporaryPath;
                $extension = $mimeType === 'image/jpeg' ? 'jpg' : substr($mimeType, 6);
            } else {
                $maxFileBytes = $this->softLimitBytes('max_file_mb', self::MAX_FILE_BYTES);
                if ($size > $maxFileBytes || $mimeType !== 'application/pdf' || $originalExtension !== 'pdf') {
                    $this->rejectFile();
                }

                $this->scanPdf($file->getRealPath());
                $sourcePath = $file->getRealPath();
                $extension = 'pdf';
                $scanState = 'clean';
            }

            $hash = hash_file('sha256', $sourcePath);
            $storedSize = filesize($sourcePath);
            if (! is_string($hash) || ! is_int($storedSize) || $storedSize < 1) {
                throw new RuntimeException('Unable to calculate content media metadata.');
            }

            $diskName = $this->diskName();
            $publicId = (string) Str::uuid();
            $originalName = $this->safeOriginalName($file);

            $media = app(EnforcesPlanLimits::class)->create(
                $workspace,
                PlanMetric::StorageBytes,
                function (Workspace $lockedWorkspace) use (
                    $purpose,
                    $mimeType,
                    $extension,
                    $sourcePath,
                    $publicId,
                    $diskName,
                    $user,
                    $originalName,
                    $storedSize,
                    $hash,
                    $scanState,
                ): ContentMedia {
                    $directory = "workspaces/{$lockedWorkspace->getKey()}/content-media";
                    $path = "{$directory}/{$publicId}.{$extension}";
                    $disk = Storage::disk($diskName);

                    try {
                        $storedPath = $disk->putFileAs($directory, $sourcePath, basename($path), ['visibility' => 'private']);
                        if (! is_string($storedPath)) {
                            throw new RuntimeException('Unable to store content media.');
                        }

                        return ContentMedia::query()->create([
                            'workspace_id' => $lockedWorkspace->getKey(),
                            'uploaded_by' => $user->getKey(),
                            'public_id' => $publicId,
                            'disk' => $diskName,
                            'path' => $storedPath,
                            'original_name' => $originalName,
                            'mime_type' => $mimeType,
                            'byte_size' => $storedSize,
                            'sha256' => $hash,
                            'visibility' => 'private',
                            'scan_state' => $scanState,
                            'purpose' => $purpose,
                            'expires_at' => $purpose->retainsIndefinitely()
                                ? null
                                : now('UTC')->addDays($this->retentionDays()),
                        ]);
                    } catch (Throwable $exception) {
                        if ($disk->exists($path)) {
                            $disk->delete($path);
                        }

                        throw $exception;
                    }
                },
                $storedSize,
            );
            if ($scopedAdmin) {
                app(AdminContentMutation::class)->onRollback(request(), fn () => Storage::disk($media->disk)->delete($media->path));
            }

            return $media;
        } finally {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    public function storePlatformAsset(UploadedFile $file, ?User $actor): ContentMedia
    {
        if (! $file->isValid() || ! is_string($file->getRealPath())) {
            $this->rejectFile();
        }

        $size = $file->getSize();
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

        if (! is_int($size) || $size < 1 || ! is_string($mimeType)) {
            $this->rejectFile();
        }

        $originalExtension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        if (
            $size > $this->softLimitBytes('max_image_mb', self::MAX_IMAGE_BYTES)
            || ! isset(self::IMAGE_EXTENSIONS[$mimeType])
            || ! in_array($originalExtension, self::IMAGE_EXTENSIONS[$mimeType], true)
        ) {
            $this->rejectFile();
        }

        $temporaryPath = $this->reencodeImage($file->getRealPath(), $mimeType);

        try {
            $hash = hash_file('sha256', $temporaryPath);
            $storedSize = filesize($temporaryPath);

            if (! is_string($hash) || ! is_int($storedSize) || $storedSize < 1) {
                throw new RuntimeException('Unable to calculate content media metadata.');
            }

            $diskName = $this->diskName();
            $publicId = (string) Str::uuid();
            $extension = $mimeType === 'image/jpeg' ? 'jpg' : substr($mimeType, 6);
            $path = "platform-assets/{$publicId}.{$extension}";
            $disk = Storage::disk($diskName);

            try {
                $storedPath = $disk->putFileAs('platform-assets', $temporaryPath, basename($path), ['visibility' => 'private']);

                if (! is_string($storedPath)) {
                    throw new RuntimeException('Unable to store platform asset.');
                }

                return ContentMedia::query()->create([
                    'workspace_id' => null,
                    'uploaded_by' => $actor?->getKey(),
                    'public_id' => $publicId,
                    'disk' => $diskName,
                    'path' => $storedPath,
                    'original_name' => $this->safeOriginalName($file),
                    'mime_type' => $mimeType,
                    'byte_size' => $storedSize,
                    'sha256' => $hash,
                    'visibility' => 'private',
                    'scan_state' => 'not_required',
                    'purpose' => MediaPurpose::PlatformAsset,
                    'expires_at' => null,
                ]);
            } catch (Throwable $exception) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }

                throw $exception;
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    public function copyToPlatformAsset(ContentMedia $source, ?User $actor = null): ContentMedia
    {
        $disk = Storage::disk($source->disk);

        if (! $disk->exists($source->path)) {
            throw new RuntimeException('The legacy media object is unavailable.');
        }

        $stream = $disk->readStream($source->path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The legacy media object is unreadable.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'linkpilot-asset-');

        if (! is_string($temporaryPath)) {
            fclose($stream);
            throw new RuntimeException('Unable to prepare the legacy media copy.');
        }

        try {
            $target = fopen($temporaryPath, 'wb');

            if (! is_resource($target)) {
                throw new RuntimeException('Unable to prepare the legacy media copy.');
            }

            try {
                $copied = stream_copy_to_stream($stream, $target);
            } finally {
                fclose($target);
            }

            if (! is_int($copied) || $copied < 1) {
                throw new RuntimeException('Unable to copy the legacy media object.');
            }

            $upload = new UploadedFile($temporaryPath, $source->original_name, $source->mime_type, null, true);

            return $this->storePlatformAsset($upload, $actor);
        } finally {
            fclose($stream);
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    public function purge(ContentMedia $media): void
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->forceDelete();
    }

    public function delete(ContentMedia $media): void
    {
        if (! $media->trashed()) {
            $media->delete();
        }
    }

    public function deleteIfUnreferencedPlatformAsset(?int $mediaId): void
    {
        if ($mediaId === null || $this->settings->assetReferenced($mediaId)) {
            return;
        }

        $media = ContentMedia::query()
            ->whereKey($mediaId)
            ->where('purpose', MediaPurpose::PlatformAsset)
            ->first();

        if ($media instanceof ContentMedia) {
            $this->delete($media);
        }
    }

    private function reencodeImage(string $sourcePath, string $mimeType): string
    {
        try {
            $info = $this->imageInfo($sourcePath);
        } catch (ErrorException) {
            $this->rejectFile();
        }

        if (
            $info === false
            || $info['mime'] !== $mimeType
            || $info[0] < 1
            || $info[1] < 1
            || $info[0] > intdiv(self::MAX_IMAGE_PIXELS, $info[1])
        ) {
            $this->rejectFile();
        }

        $bytes = file_get_contents($sourcePath);
        if (! is_string($bytes)) {
            $this->rejectFile();
        }

        $image = $this->decodeImage($bytes);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'linkpilot-media-');
        if (! is_string($temporaryPath)) {
            imagedestroy($image);
            throw new RuntimeException('Unable to prepare content media.');
        }

        try {
            $written = match ($mimeType) {
                'image/jpeg' => imagejpeg($image, $temporaryPath, 90),
                'image/png' => imagepng($image, $temporaryPath, 6),
                'image/webp' => function_exists('imagewebp') && imagewebp($image, $temporaryPath, 90),
                default => false,
            };
        } finally {
            imagedestroy($image);
        }

        if (! $written || ! is_file($temporaryPath) || filesize($temporaryPath) < 1) {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }

            $this->rejectFile();
        }

        return $temporaryPath;
    }

    /**
     * @return array{0:int,1:int,2:int,3:string,mime:string,channels?:int,bits?:int}|false
     *
     * @throws ErrorException
     */
    private function imageInfo(string $path): array|false
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            return getimagesize($path);
        } finally {
            restore_error_handler();
        }
    }

    private function decodeImage(string $bytes): \GdImage
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $image = imagecreatefromstring($bytes);
        } catch (ErrorException) {
            $image = false;
        } finally {
            restore_error_handler();
        }

        if (! $image instanceof \GdImage) {
            $this->rejectFile();
        }

        return $image;
    }

    private function scanPdf(string $path): void
    {
        $scanner = (string) config('services.linkpilot.media.clamav_path', 'clamscan');
        if (trim($scanner) === '') {
            $this->rejectFile();
        }

        $process = new Process([$scanner, '--no-summary', $path]);
        $process->setTimeout(60);
        $process->disableOutput();

        try {
            $process->run();
        } catch (ProcessStartFailedException|ProcessTimedOutException) {
            $this->rejectFile();
        }

        if (! $process->isSuccessful()) {
            $this->rejectFile();
        }
    }

    private function diskName(): string
    {
        if ($this->settings->hasStored('uploading', 'disk')) {
            $configured = $this->settings->resolved()['uploading']['disk'] ?? null;
            if (is_string($configured) && config('filesystems.disks.'.$configured) !== null) {
                if (! $this->isPrivateDisk($configured)) {
                    throw new RuntimeException('Content media storage must use a private disk.');
                }

                return $configured;
            }
        }

        $disk = config('services.linkpilot.media.disk');
        if (! is_string($disk) || trim($disk) === '') {
            if (app()->environment('production')) {
                throw new RuntimeException('Content media storage is not configured.');
            }

            $disk = 'local';
        }

        if (! is_array(config("filesystems.disks.{$disk}"))) {
            throw new RuntimeException('Content media storage disk is not configured.');
        }

        if (! $this->isPrivateDisk($disk)) {
            throw new RuntimeException('Content media storage must use a private disk.');
        }

        return $disk;
    }

    private function isPrivateDisk(string $disk): bool
    {
        $configuration = config('filesystems.disks.'.$disk);

        if (! is_array($config = $configuration)) {
            return false;
        }

        if (! in_array($config['driver'] ?? null, ['local', 's3'], true) || ($config['visibility'] ?? null) === 'public') {
            return false;
        }

        $root = $config['root'] ?? null;

        return ! (is_string($root)
            && realpath($root) !== false
            && str_starts_with((string) realpath($root), (string) realpath(public_path())));
    }

    private function softLimitBytes(string $key, int $hardLimit): int
    {
        if (! $this->settings->hasStored('uploading', $key)) {
            return $hardLimit;
        }

        $megabytes = (int) ($this->settings->resolved()['uploading'][$key] ?? 0);

        return $megabytes > 0 ? min($hardLimit, $megabytes * 1024 * 1024) : $hardLimit;
    }

    private function retentionDays(): int
    {
        if ($this->settings->hasStored('uploading', 'retention_days')) {
            return max(1, (int) ($this->settings->resolved()['uploading']['retention_days'] ?? 365));
        }

        return max(1, (int) config('services.linkpilot.media.retention_days', 365));
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
        $name = trim($name, " .\t\n\r\0\x0B");

        return $name === '' ? 'upload' : mb_substr($name, 0, 255);
    }

    private function rejectFile(): never
    {
        throw ValidationException::withMessages([
            'file' => 'The file is unsupported or could not pass the required security checks.',
        ]);
    }
}
