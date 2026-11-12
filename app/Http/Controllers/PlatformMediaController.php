<?php

namespace App\Http\Controllers;

use App\Models\ContentMedia;
use App\Services\Settings\PlatformSettings;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformMediaController extends Controller
{
    public function __invoke(int $media, PlatformSettings $settings): StreamedResponse
    {
        $payload = $settings->resolved();
        $ids = [
            $payload['general']['logo_light_id'] ?? null,
            $payload['general']['logo_dark_id'] ?? null,
            $payload['general']['compact_logo_id'] ?? null,
            $payload['general']['favicon_id'] ?? null,
            $payload['biolinks']['branding_media_id'] ?? null,
            $payload['seo']['og_media_id'] ?? null,
        ];
        $allowed = array_filter(array_map('intval', $ids));

        abort_unless(in_array($media, $allowed, true), 404);

        $file = ContentMedia::query()
            ->whereKey($media)
            ->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])
            ->whereIn('scan_state', ['not_required', 'clean'])
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now('UTC')))
            ->firstOrFail();

        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), 404);

        return $disk->response($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}