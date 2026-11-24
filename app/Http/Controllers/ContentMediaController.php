<?php

namespace App\Http\Controllers;

use App\Models\ContentMedia;
use App\Models\LinkPageBlock;
use App\Services\LinkPages\LinkPageVariantAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentMediaController extends Controller
{
    public function __construct(private readonly LinkPageVariantAssignment $variants) {}

    public function __invoke(Request $request, string $publicId): StreamedResponse
    {
        $now = now('UTC');
        $media = ContentMedia::query()
            ->where('public_id', $publicId)
            ->where('visibility', 'private')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->firstOrFail();

        $block = LinkPageBlock::query()
            ->where('content_media_id', $media->id)
            ->whereIn('type', ['image', 'file'])
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->whereHas('linkPage', fn ($query) => $query
                ->where('workspace_id', $media->workspace_id)
                ->where('is_published', true))
            ->with('linkPage')
            ->firstOrFail();
        $variant = $this->variants->assigned($request, $block->linkPage);
        abort_unless($block->link_page_variant_id === $variant->id, 404);

        $isImage = $block->type === 'image'
            && in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)
            && in_array($media->scan_state, ['not_required', 'clean'], true);
        $isFile = $block->type === 'file'
            && $media->mime_type === 'application/pdf'
            && $media->scan_state === 'clean';
        abort_unless($isImage || $isFile, 404);

        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->path), 404);

        $contentDisposition = 'inline';
        if ($isFile) {
            $filenameFallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $media->displayName()) ?: 'download.pdf';
            $contentDisposition = HeaderUtils::makeDisposition(
                'attachment',
                $media->displayName(),
                $filenameFallback,
            );
        }

        $headers = [
            'Content-Type' => $media->mime_type,
            'Content-Length' => (string) $media->byte_size,
            'Content-Disposition' => $contentDisposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        try {
            $stream = $disk->readStream($media->path);
        } catch (UnableToReadFile) {
            abort(404);
        }
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, $headers);
    }
}
