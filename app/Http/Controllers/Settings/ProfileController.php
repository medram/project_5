<?php

namespace App\Http\Controllers\Settings;

use App\Enums\MediaPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\ContentMedia;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Accounts\AccountProfileOptions;
use App\Services\Accounts\GoogleConnection;
use App\Services\Content\ContentMediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AccountProfileOptions $options,
        private readonly GoogleConnection $google,
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('avatarMedia');
        $googleAccount = $this->google->accountFor($user);

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => true,
            'status' => $request->session()->get('status'),
            'avatarUrl' => $user->avatarMedia !== null
                ? route('account.avatar', $user->avatarMedia->public_id)
                : null,
            'profile' => [
                'locale' => $user->locale,
                'countryCode' => $user->country_code,
                'timezone' => $user->timezone,
            ],
            'options' => [
                'locales' => $this->options->locales(),
                'countries' => $this->options->countries(),
                'timezones' => $this->options->timezones(),
            ],
            'google' => [
                'enabled' => $this->google->enabled(),
                'connected' => $googleAccount !== null,
                'email' => $googleAccount?->email,
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, ContentMediaStorage $media): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $avatarFile = $request->file('avatar');
        $removeAvatar = (bool) ($data['remove_avatar'] ?? false);
        unset($data['avatar'], $data['remove_avatar']);

        $newMedia = null;

        if ($avatarFile !== null) {
            /** @var Workspace $personalWorkspace */
            $personalWorkspace = $user->personalWorkspace()->firstOrFail();

            try {
                $newMedia = $media->store(
                    $avatarFile,
                    $personalWorkspace,
                    $user,
                    MediaPurpose::AccountAvatar,
                );
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    'avatar' => $exception->errors()['file'][0] ?? 'The avatar could not be processed.',
                ]);
            }
        }

        $oldMedia = null;
        $emailChanged = false;

        try {
            DB::transaction(function () use ($user, $data, $newMedia, $removeAvatar, &$oldMedia, &$emailChanged): void {
                $user->fill($data);
                $emailChanged = $user->isDirty('email');

                if ($emailChanged) {
                    $user->email_verified_at = null;
                }

                $oldMedia = $user->avatarMedia()->first();

                if ($newMedia !== null) {
                    $user->avatar_media_id = $newMedia->getKey();
                } elseif ($removeAvatar) {
                    $user->avatar_media_id = null;
                }

                $user->save();
            });
        } catch (Throwable $exception) {
            if ($newMedia instanceof ContentMedia) {
                $media->delete($newMedia);
            }

            throw $exception;
        }

        if ($oldMedia !== null && $oldMedia->getKey() !== $newMedia?->getKey()) {
            $media->delete($oldMedia);
        }

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $ownedWorkspaces = Workspace::query()
                ->where('owner_id', $user->getKey())
                ->lockForUpdate()
                ->get();

            foreach ($ownedWorkspaces as $workspace) {
                $hasOtherMembers = DB::table('workspace_user')
                    ->where('workspace_id', $workspace->getKey())
                    ->where('user_id', '!=', $user->getKey())
                    ->exists();

                if ($hasOtherMembers) {
                    throw ValidationException::withMessages([
                        'workspaces' => "Transfer ownership of {$workspace->name} to another member before deleting your account.",
                    ]);
                }
            }

            foreach ($ownedWorkspaces as $workspace) {
                foreach (['analytics_events', 'custom_domains', 'webhooks', 'tracking_pixels', 'link_pages', 'link_overlays', 'bio_pages', 'folders', 'links'] as $table) {
                    DB::table($table)->where('workspace_id', $workspace->getKey())->delete();
                }

                $workspace->delete();
            }

            $user->refresh();
            Auth::logout();
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
