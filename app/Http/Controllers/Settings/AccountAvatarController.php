<?php

namespace App\Http\Controllers\Settings;

use App\Enums\MediaPurpose;
use App\Http\Controllers\Controller;
use App\Models\ContentMedia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountAvatarController extends Controller
{
    public function show(Request $request, string $publicId): StreamedResponse
    {
        $media = ContentMedia::query()
            ->where('public_id', $publicId)
            ->where('purpose', MediaPurpose::AccountAvatar)
            ->firstOrFail();

        $owner = User::query()->where('avatar_media_id', $media->getKey())->firstOrFail();
        $viewer = $request->user();

        abort_unless(
            $viewer !== null && ($viewer->sharesWorkspaceWith($owner) || $viewer->is_admin),
            403,
        );

        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->path), 404);

        return $disk->response($media->path, $media->original_name, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
