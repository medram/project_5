<?php

namespace App\Http\Controllers;

use App\Models\BioContentBlock;
use App\Models\BioPage;
use App\Services\BioPages\BioPagePresentation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicBioContentMediaController extends Controller
{
    public function __construct(private readonly BioPagePresentation $presentation) {}

    public function __invoke(Request $request, string $handle, BioContentBlock $bioBlock): StreamedResponse
    {
        $page = BioPage::query()->where('handle', $handle)->where('is_published', true)->firstOrFail();
        abort_unless($bioBlock->bio_page_id === $page->id && $bioBlock->type === 'image' && $this->presentation->eligible($bioBlock), 404);
        $media = $bioBlock->contentMedia()->where('workspace_id', $page->workspace_id)->whereNull('deleted_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now('UTC')))
            ->where('visibility', 'private')->whereIn('scan_state', ['not_required', 'clean'])->firstOrFail();
        abort_unless(in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->path), 404);

        return response()->stream(function () use ($disk, $media): void {
            $stream = $disk->readStream($media->path);
            if (! is_resource($stream)) {
                abort(404);
            }
            fpassthru($stream);
            fclose($stream);
        }, 200, ['Content-Type' => $media->mime_type, 'Content-Length' => (string) $media->byte_size, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
