<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaPurpose;
use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Models\ContentMedia;
use App\Services\Content\ContentMediaStorage;
use App\Services\Platform\AdminAudit;
use App\Services\Settings\PlatformSettings;
use App\Services\Settings\SettingDefinitions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Throwable;

class AdminSettingActionController extends Controller
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly AdminAudit $audit,
        private readonly SettingDefinitions $definitions,
    ) {}

    public function testEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'email', 'max:255'],
        ]);
        $this->settings->applyRuntime();

        try {
            Mail::raw('LinkPilot email configuration test.', function ($message) use ($validated): void {
                $message->to($validated['recipient'])->subject('LinkPilot email test');
            });
        } catch (Throwable) {
            $this->audit->record($request->user(), PlatformPermission::AdminSettingsManage, 'test_platform_settings_email', null, null, ['outcome' => 'failure']);

            throw ValidationException::withMessages([
                'email' => 'The saved mail configuration could not deliver a message.',
            ]);
        }

        $this->audit->record($request->user(), PlatformPermission::AdminSettingsManage, 'test_platform_settings_email', null, null, ['outcome' => 'success']);

        return back()->with('success', 'Test email accepted for delivery.');
    }

    public function testStripe(Request $request): RedirectResponse
    {
        $this->settings->applyRuntime();

        if (! (bool) ($this->settings->resolved()['subscriptions']['billing_enabled'] ?? false)) {
            throw ValidationException::withMessages(['stripe' => 'Billing is disabled.']);
        }

        try {
            Cashier::stripe()->balance->retrieve();
        } catch (Throwable) {
            $this->audit->record($request->user(), PlatformPermission::AdminSettingsManage, 'test_platform_settings_stripe', null, null, ['outcome' => 'failure']);

            throw ValidationException::withMessages([
                'stripe' => 'The saved Stripe configuration could not be validated.',
            ]);
        }

        $this->audit->record($request->user(), PlatformPermission::AdminSettingsManage, 'test_platform_settings_stripe', null, null, ['outcome' => 'success']);

        return back()->with('success', 'Stripe credentials were accepted.');
    }

    public function clearSecret(Request $request, string $section, string $key): RedirectResponse
    {
        abort_unless($this->definitions->isSecret($section, $key), 404);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
        ]);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $this->settings->clearSecret($actor, $section, $key, (int) $validated['version']);

        return back()->with('success', 'Stored secret removed.');
    }

    public function uploadAsset(
        Request $request,
        string $section,
        string $key,
        ContentMediaStorage $storage,
    ): RedirectResponse {
        abort_unless(($this->definitions->fields($section)[$key]['asset'] ?? false) === true, 404);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $candidate = $storage->storePlatformAsset($validated['file'], $actor);
        $previous = $this->settings->resolved()[$section][$key] ?? null;

        try {
            $this->settings->updateAsset(
                $actor,
                $section,
                $key,
                $candidate->id,
                (int) $validated['version'],
            );
        } catch (Throwable $exception) {
            $storage->purge($candidate);

            throw $exception;
        }

        if (is_int($previous) && $previous !== $candidate->id && ! $this->settings->assetReferenced($previous)) {
            $old = ContentMedia::query()
                ->whereKey($previous)
                ->where('purpose', MediaPurpose::PlatformAsset)
                ->first();

            if ($old instanceof ContentMedia) {
                $storage->delete($old);
            }
        }

        return back()->with('success', 'Platform asset updated.');
    }
}
