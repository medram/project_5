<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaPurpose;
use App\Http\Controllers\Controller;
use App\Models\ContentMedia;
use App\Models\CustomPage;
use App\Models\Locale;
use App\Services\Accounts\AccountProfileOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingOptionController extends Controller
{
    public function __invoke(
        Request $request,
        string $source,
        AccountProfileOptions $profiles,
    ): JsonResponse {
        $query = trim((string) $request->query('q', ''));
        $selected = collect($request->query('selected', []))
            ->filter(fn (mixed $value): bool => is_scalar($value))
            ->map(fn (mixed $value): string => (string) $value)
            ->take(50)
            ->all();

        $items = match ($source) {
            'platform-assets' => $this->platformAssets($query, $selected),
            'published-pages' => $this->publishedPages($query, $selected),
            'locales' => $this->locales($query, $selected),
            'timezones' => $this->timezones($query, $selected, $profiles),
            'storage-disks' => $this->storageDisks($query, $selected),
            default => null,
        };

        abort_if($items === null, 404);

        return response()->json(['items' => array_slice($items, 0, 50)]);
    }

    /**
     * @param  list<string>  $selected
     * @return list<array{value: string, label: string, description: string|null}>
     */
    private function platformAssets(string $query, array $selected): array
    {
        $rows = ContentMedia::query()
            ->where('purpose', MediaPurpose::PlatformAsset)
            ->whereNull('workspace_id')
            ->where('visibility', 'private')
            ->whereIn('scan_state', ['not_required', 'clean'])
            ->whereNull('expires_at')
            ->when($query !== '', fn ($builder) => $builder->where('original_name', 'like', '%'.$query.'%'))
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'original_name', 'mime_type']);

        if ($selected !== []) {
            $rows = $rows->merge(
                ContentMedia::query()
                    ->whereIn('id', array_map('intval', $selected))
                    ->where('purpose', MediaPurpose::PlatformAsset)
                    ->whereNull('workspace_id')
                    ->where('visibility', 'private')
                    ->whereIn('scan_state', ['not_required', 'clean'])
                    ->whereNull('expires_at')
                    ->get(['id', 'original_name', 'mime_type']),
            )->unique('id');
        }

        return $rows->map(fn (ContentMedia $media): array => [
            'value' => (string) $media->id,
            'label' => $media->original_name,
            'description' => $media->mime_type,
        ])->values()->all();
    }

    /**
     * @param  list<string>  $selected
     * @return list<array{value: string, label: string, description: string|null}>
     */
    private function publishedPages(string $query, array $selected): array
    {
        return CustomPage::query()
            ->where('state', 'published')
            ->where(function ($builder) use ($query, $selected): void {
                if ($query !== '') {
                    $builder->where('title', 'like', '%'.$query.'%');
                }

                if ($selected !== []) {
                    $builder->orWhereIn('id', array_map('intval', $selected));
                }
            })
            ->orderBy('title')
            ->limit(50)
            ->get(['id', 'title', 'slug'])
            ->map(fn (CustomPage $page): array => [
                'value' => (string) $page->id,
                'label' => $page->title,
                'description' => '/'.$page->slug,
            ])->values()->all();
    }

    /**
     * @param  list<string>  $selected
     * @return list<array{value: string, label: string, description: string|null}>
     */
    private function locales(string $query, array $selected): array
    {
        $rows = Locale::query()
            ->where('is_published', true)
            ->where(function ($builder) use ($query, $selected): void {
                if ($query !== '') {
                    $builder->where('name', 'like', '%'.$query.'%');
                }

                if ($selected !== []) {
                    $builder->orWhereIn('code', $selected);
                }
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['code', 'name']);

        if ($rows->isEmpty()) {
            return collect((array) config('linkpilot.supported_locales', []))
                ->filter(fn (string $name, string $code): bool => $query === '' || str_contains(strtolower($name), strtolower($query)) || in_array($code, $selected, true))
                ->take(50)
                ->map(fn (string $name, string $code): array => [
                    'value' => $code,
                    'label' => $name,
                    'description' => $code,
                ])->values()->all();
        }

        return $rows
            ->map(fn (Locale $locale): array => [
                'value' => $locale->code,
                'label' => $locale->name,
                'description' => $locale->code,
            ])->values()->all();
    }

    /**
     * @param  list<string>  $selected
     * @return list<array{value: string, label: string, description: string|null}>
     */
    private function timezones(string $query, array $selected, AccountProfileOptions $profiles): array
    {
        return collect($profiles->timezones())
            ->filter(fn (string $zone): bool => $query === '' || str_contains(strtolower($zone), strtolower($query)) || in_array($zone, $selected, true))
            ->take(50)
            ->map(fn (string $zone): array => [
                'value' => $zone,
                'label' => $zone,
                'description' => null,
            ])->values()->all();
    }

    /**
     * @param  list<string>  $selected
     * @return list<array{value: string, label: string, description: string|null}>
     */
    private function storageDisks(string $query, array $selected): array
    {
        $items = [];

        foreach ((array) config('filesystems.disks', []) as $name => $disk) {
            if (! is_array($disk)) {
                continue;
            }

            $driver = $disk['driver'] ?? null;
            $visibility = $disk['visibility'] ?? null;
            $root = $disk['root'] ?? null;

            if (! in_array($driver, ['local', 's3'], true)) {
                continue;
            }

            if ($visibility === 'public') {
                continue;
            }

            if ($driver === 'local' && is_string($root) && str_starts_with((string) realpath($root), (string) realpath(public_path()))) {
                continue;
            }

            if ($query !== '' && ! str_contains(strtolower($name), strtolower($query))) {
                continue;
            }

            $items[] = [
                'value' => $name,
                'label' => ucfirst($name),
                'description' => $driver === 's3' ? 'S3-compatible' : 'Private local',
            ];
        }

        foreach ($selected as $name) {
            if (collect($items)->contains('value', $name)) {
                continue;
            }

            $disk = config('filesystems.disks.'.$name);
            $items[] = [
                'value' => $name,
                'label' => ucfirst($name),
                'description' => is_array($disk) ? 'Incomplete deployment configuration' : 'Not configured',
            ];
        }

        return array_slice($items, 0, 50);
    }
}
