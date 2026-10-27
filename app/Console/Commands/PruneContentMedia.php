<?php

namespace App\Console\Commands;

use App\Models\ContentMedia;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PruneContentMedia extends Command
{
    protected $signature = 'linkpilot:prune-content-media';

    protected $description = 'Permanently remove deleted or expired content media';

    public function handle(): int
    {
        $deleted = 0;
        $failed = 0;
        $now = now('UTC');

        ContentMedia::withTrashed()
            ->where(static function (Builder $query) use ($now): void {
                $query->whereNotNull('deleted_at')->orWhere('expires_at', '<=', $now);
            })
            ->orderBy('id')
            ->chunkById(100, function ($mediaItems) use (&$deleted, &$failed): void {
                foreach ($mediaItems as $media) {
                    try {
                        $disk = Storage::disk($media->disk);
                        if ($disk->exists($media->path) && ! $disk->delete($media->path)) {
                            $failed++;

                            continue;
                        }

                        $media->forceDelete();
                        $deleted++;
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->error("Failed to purge content media record [{$media->id}] (".get_class($exception).').');
                    }
                }
            });

        $this->info("Pruned {$deleted} content media records; failed {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
