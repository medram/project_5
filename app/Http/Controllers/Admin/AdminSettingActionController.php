<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Services\Content\ContentMediaStorage;
use App\Services\Platform\AdminAudit;
use App\Services\Settings\PlatformSettings;
use App\Services\Settings\SettingDefinitions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use RuntimeException;
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

    public function testStorage(Request $request): RedirectResponse
    {
        $this->settings->applyRuntime();

        $disk = $this->settings->hasStored('uploading', 'disk')
            ? ($this->settings->resolved()['uploading']['disk'] ?? null)
            : config('services.linkpilot.media.disk');

        if (! is_string($disk) || ! is_array(config('filesystems.disks.'.$disk))) {
            throw ValidationException::withMessages(['storage' => 'No storage disk is configured.']);
        }

        try {
            $filesystem = Storage::disk($disk);
            $path = 'healthcheck/'.Str::uuid()->toString().'.txt';

            if (! $filesystem->put($path, 'LinkPilot storage check') || ! $filesystem->exists($path)) {
                throw new RuntimeException('Storage check write failed.');
            }

            $filesystem->delete($path);
        } catch (Throwable) {
            $this->audit->record($request->user(), PlatformPermission::AdminSettingsManage, 'test_platform_settings_storage', null, null, ['outcome' => 'failure']);

            throw ValidationException::withMessages([
                'storage' => 'The saved storage configuration could not be written to.',
            ]);
        }

        $this->audit->record($request->user(), PlatformPermission::AdminSettingsManage, 'test_platform_settings_storage', null, null, ['outcome' => 'success']);

        return back()->with('success', 'Storage disk accepted a test write.');
    }

    public function clearSecret(Request $request, string $routeKey, string $key): RedirectResponse
    {
        $sectionKey = $this->definitions->sectionKeyForRoute($routeKey);
        abort_if($sectionKey === null, 404);
        abort_unless($this->definitions->isSecret($sectionKey, $key), 404);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
        ]);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $this->settings->clearSecret($actor, $sectionKey, $key, (int) $validated['version']);

        return back()->with('success', 'Stored secret removed.');
    }

    public function uploadAsset(
        Request $request,
        string $routeKey,
        string $key,
        ContentMediaStorage $storage,
    ): RedirectResponse {
        $sectionKey = $this->definitions->sectionKeyForRoute($routeKey);
        abort_if($sectionKey === null, 404);
        abort_unless(($this->definitions->fields($sectionKey)[$key]['asset'] ?? false) === true, 404);

        $validated = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $candidate = $storage->storePlatformAsset($validated['file'], $actor);
        $previous = $this->settings->resolved()[$sectionKey][$key] ?? null;

        try {
            $this->settings->updateAsset(
                $actor,
                $sectionKey,
                $key,
                $candidate->id,
                (int) $validated['version'],
            );
        } catch (Throwable $exception) {
            $storage->purge($candidate);

            throw $exception;
        }

        $storage->deleteIfUnreferencedPlatformAsset(is_int($previous) ? $previous : null);

        return back()->with('success', 'Platform asset updated.');
    }
}
