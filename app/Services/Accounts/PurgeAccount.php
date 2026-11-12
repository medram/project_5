<?php

namespace App\Services\Accounts;

use App\Models\ContentMedia;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PurgeAccount
{
    public function __construct(private readonly ScheduleAccountDeletion $schedule) {}

    public function purge(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $locked = User::query()->lockForUpdate()->find($user->getKey());
            /** @var User|null $locked */
            if ($locked === null || $locked->purge_after === null || $locked->purge_after->isFuture()) {
                return false;
            }

            try {
                $this->schedule->assertCanSchedule($locked);
            } catch (ValidationException $exception) {
                Log::info('account.purge.skipped', [
                    'user_id' => $locked->getKey(),
                    'reasons' => array_keys($exception->errors()),
                ]);

                return false;
            }

            $this->deleteOwnedWorkspaces($locked);

            DB::table('workspace_user')->where('user_id', $locked->getKey())->delete();
            DB::table('sessions')->where('user_id', $locked->getKey())->delete();
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $locked->getKey())
                ->delete();
            DB::table('notification_preferences')->where('user_id', $locked->getKey())->delete();

            $locked->socialAccounts()->delete();
            $locked->tokens()->delete();
            $locked->forceFill(['remember_token' => null])->save();
            $locked->delete();

            return true;
        }, attempts: 3);
    }

    private function deleteOwnedWorkspaces(User $user): void
    {
        /** @var Collection<int, Workspace> $workspaces */
        $workspaces = Workspace::query()
            ->where('owner_id', $user->getKey())
            ->lockForUpdate()
            ->get();

        foreach ($workspaces as $workspace) {
            $this->deleteWorkspaceMedia($workspace);

            foreach (['analytics_events', 'custom_domains', 'webhooks', 'tracking_pixels', 'link_pages', 'link_overlays', 'bio_pages', 'folders', 'links'] as $table) {
                DB::table($table)->where('workspace_id', $workspace->getKey())->delete();
            }

            $workspace->delete();
        }
    }

    private function deleteWorkspaceMedia(Workspace $workspace): void
    {
        $mediaItems = ContentMedia::withTrashed()
            ->where('workspace_id', $workspace->getKey())
            ->get();

        foreach ($mediaItems as $media) {
            try {
                $disk = Storage::disk($media->disk);

                if ($disk->exists($media->path)) {
                    $disk->delete($media->path);
                }
            } catch (Throwable $exception) {
                Log::warning('account.purge.media_delete_failed', [
                    'media_id' => $media->getKey(),
                    'reason' => class_basename($exception),
                ]);
            }

            $media->forceDelete();
        }
    }
}
