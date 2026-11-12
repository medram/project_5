<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminSettingsUpdateRequest;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingController extends Controller
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function show(Request $request, string $section): Response
    {
        $schema = $this->settings->schema($section);
        abort_if($schema === null, 404);

        return Inertia::render('admin/settings/section', [
            'section' => $schema,
            'navigation' => $this->settings->navigation(),
        ]);
    }

    public function update(AdminSettingsUpdateRequest $request, string $section): RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor === null, 403);

        $this->settings->updateSection(
            $actor,
            $section,
            $request->validated()['values'],
            (int) $request->validated()['version'],
        );

        return back();
    }
}