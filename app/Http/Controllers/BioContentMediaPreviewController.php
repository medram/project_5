<?php

namespace App\Http\Controllers;

use App\Models\BioContentBlock;
use App\Models\BioPage;
use App\Services\Platform\AdminContentAccess;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BioContentMediaPreviewController extends Controller
{
    public function __construct(private readonly WorkspaceContext $workspaces) {}

    public function __invoke(Request $request, BioPage $bioPage, BioContentBlock $bioBlock): StreamedResponse
    {
        $workspace = $this->workspaces->forContentRequest($request);
        abort_unless((int) $bioPage->workspace_id === (int) $workspace->id, 404);
        app(AdminContentAccess::class)->authorize($request, 'view', $bioPage);
        abort_unless($bioBlock->bio_page_id === $bioPage->id && $bioBlock->type === 'image', 404);

        $media = $bioBlock->contentMedia()
            ->where('workspace_id', $workspace->id)
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now('UTC')))
            ->where('visibility', 'private')
            ->whereIn('scan_state', ['not_required', 'clean'])
            ->firstOrFail();
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
