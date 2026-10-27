<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

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
