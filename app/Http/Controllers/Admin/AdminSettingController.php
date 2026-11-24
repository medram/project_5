<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminSettingsUpdateRequest;
use App\Models\User;
use App\Services\Platform\PlatformAuthorization;
use App\Services\Settings\PlatformSettings;
use App\Services\Content\ContentMediaStorage;
use App\Services\Settings\SettingDefinitions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingController extends Controller
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly SettingDefinitions $definitions,
        private readonly ContentMediaStorage $mediaStorage,
    ) {}

    public function show(Request $request, string $section): Response
    {
        $schema = $this->settings->schema($section);
        abort_if($schema === null, 404);

        return Inertia::render('admin/settings/section', [
            'section' => $schema,
            'navigation' => $this->settings->navigation(),
            'canManage' => $request->user() instanceof User
                && app(PlatformAuthorization::class)->allows($request->user(), PlatformPermission::AdminSettingsManage),
        ]);
    }

    public function update(AdminSettingsUpdateRequest $request, string $section): RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor === null, 403);

        $values = $request->validated()['values'];
        $assetKeys = array_keys(array_filter(
            $this->definitions->fields($section),
            fn (array $field): bool => ($field['asset'] ?? false) === true,
        ));
        $previousAssets = array_intersect_key(
            $this->settings->resolved()[$section] ?? [],
            array_fill_keys($assetKeys, true),
        );

        $this->settings->updateSection(
            $actor,
            $section,
            $values,
            (int) $request->validated()['version'],
        );

        foreach ($assetKeys as $key) {
            $previous = $previousAssets[$key] ?? null;

            if (
                is_int($previous)
                && array_key_exists($key, $values)
                && ($values[$key] === null || (int) $values[$key] !== $previous)
            ) {
                $this->mediaStorage->deleteIfUnreferencedPlatformAsset($previous);
            }
        }

        return back();
    }
}
