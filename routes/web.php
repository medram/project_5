<?php

use App\Http\Controllers\AcceptWorkspaceInvitationController;
use App\Http\Controllers\AccountAnalyticsController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminCustomPageController;
use App\Http\Controllers\Admin\AdminFileController;
use App\Http\Controllers\Admin\AdminHomeController;
use App\Http\Controllers\Admin\AdminInsightsController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminUserActionController;
use App\Http\Controllers\Admin\AdminUserIndexController;
use App\Http\Controllers\Admin\AdminUserShowController;
use App\Http\Controllers\AnalyticsEventController;
use App\Http\Controllers\AnalyticsEventDefinitionController;
use App\Http\Controllers\AnalyticsExportController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\CheckoutResultController;
use App\Http\Controllers\Billing\PortalController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BioContentBlockController;
use App\Http\Controllers\BioContentMediaPreviewController;
use App\Http\Controllers\BioLinkController;
use App\Http\Controllers\BioPageController;
use App\Http\Controllers\BioPageInsightsController;
use App\Http\Controllers\ContentMediaController;
use App\Http\Controllers\CustomDomainController;
use App\Http\Controllers\CustomDomainVerificationController;
use App\Http\Controllers\DomainChallengeController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\LinkAccessController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\LinkCsvController;
use App\Http\Controllers\LinkInsightsController;
use App\Http\Controllers\LinkOverlayController;
use App\Http\Controllers\LinkPageBlockController;
use App\Http\Controllers\LinkPageController;
use App\Http\Controllers\LinkPageInsightsController;
use App\Http\Controllers\LinkPageItemController;
use App\Http\Controllers\LinkPageLeadController;
use App\Http\Controllers\LinkPageVariantController;
use App\Http\Controllers\LinkQrCodeController;
use App\Http\Controllers\LinkRevisionController;
use App\Http\Controllers\LinkTagController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\PublicBioContentBlockController;
use App\Http\Controllers\PublicBioContentMediaController;
use App\Http\Controllers\PublicCustomPageController;
use App\Http\Controllers\PublicBioLinkController;
use App\Http\Controllers\PublicBioPageController;
use App\Http\Controllers\PublicLinkController;
use App\Http\Controllers\PublicLinkOverlayController;
use App\Http\Controllers\PublicLinkPageController;
use App\Http\Controllers\PublicLinkPageItemController;
use App\Http\Controllers\PublicLinkPageLeadController;
use App\Http\Controllers\PublicQrCodeController;
use App\Http\Controllers\QrAccessController;
use App\Http\Controllers\QrCampaignController;
use App\Http\Controllers\QrCodeAssetController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\QrCodeCsvController;
use App\Http\Controllers\QrCodeInsightsController;
use App\Http\Controllers\TrackingConsentController;
use App\Http\Controllers\TrackingPixelController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WebhookDeliveryController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceInvitationController;
use App\Http\Controllers\WorkspaceMemberController;
use App\Http\Controllers\WorkspaceSearchController;
use App\Http\Controllers\WorkspaceTransferController;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

Route::middleware('guest')->group(function (): void {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
});

Route::get('up', function (Request $request) {
    $exception = null;

    try {
        Event::dispatch(new DiagnosingHealth);
    } catch (Throwable $error) {
        if (app()->hasDebugModeEnabled()) {
            throw $error;
        }

        report($error);
        $exception = $error->getMessage();
    }

    $status = $exception === null ? 200 : 500;

    if ($request->expectsJson()) {
        return response()->json(['status' => $exception === null ? 'up' : 'down'], $status);
    }

    return response(View::file(base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/health-up.blade.php'), [
        'exception' => $exception,
    ]), status: $status);
})->name('health');

Route::inertia('/', 'welcome')->name('home');
Route::get('pricing', PricingController::class)->name('pricing');

Route::middleware(['auth', 'verified', 'active-account'])->group(function (): void {
    Route::get('dashboard', fn () => to_route('links.index'))->name('dashboard');
    Route::get('search', WorkspaceSearchController::class)->name('search.index');
    Route::get('notifications', [NotificationCenterController::class, 'index'])
        ->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationCenterController::class, 'read'])
        ->name('notifications.read');
    Route::post('notifications/read-all', [NotificationCenterController::class, 'readAll'])
        ->name('notifications.read-all');
    Route::patch('notifications/preferences', [NotificationCenterController::class, 'updatePreferences'])
        ->name('notifications.preferences.update');
    Route::get('dashboard/links', [LinkController::class, 'index'])->name('links.index');
    Route::get('account-settings/workspaces', [WorkspaceController::class, 'index'])
        ->name('account-settings.workspaces');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])
        ->name('workspaces.switch');
    Route::get('workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'index'])
        ->name('workspaces.invitations.index');
    Route::post('workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store'])
        ->name('workspaces.invitations.store');
    Route::post('workspaces/{workspace}/invitations/{invitation}/resend', [WorkspaceInvitationController::class, 'resend'])
        ->name('workspaces.invitations.resend');
    Route::delete('workspaces/{workspace}/invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy'])
        ->name('workspaces.invitations.destroy');
    Route::patch('workspaces/{workspace}/members/{member}', [WorkspaceMemberController::class, 'update'])
        ->name('workspaces.members.update');
    Route::delete('workspaces/{workspace}/members/{member}', [WorkspaceMemberController::class, 'destroy'])
        ->name('workspaces.members.destroy');
    Route::post('workspaces/{workspace}/transfer', WorkspaceTransferController::class)
        ->name('workspaces.transfer');
    Route::get('dashboard/qr-codes', [QrCodeController::class, 'index'])->name('qr-codes.index');
    Route::get('dashboard/qr-codes/draft-preview.svg', [QrCodeAssetController::class, 'draftPreview'])
        ->name('qr-codes.draft-preview');
    Route::post('dashboard/qr-codes', [QrCampaignController::class, 'store'])->name('qr-codes.store');
    Route::patch('dashboard/qr-codes/{qrCode}', [QrCampaignController::class, 'update'])
        ->name('qr-codes.update');
    Route::delete('dashboard/qr-codes/{qrCode}', [QrCampaignController::class, 'destroy'])
        ->name('qr-codes.destroy');
    Route::get('dashboard/qr-codes/export', QrCodeCsvController::class)->name('qr-codes.export');
    Route::get('dashboard/qr-codes/insights', [QrCodeInsightsController::class, 'workspace'])
        ->name('qr-codes.insights');
    Route::get('dashboard/qr-codes/{qrCode}/preview.svg', [QrCodeAssetController::class, 'preview'])
        ->name('qr-codes.preview');
    Route::get('dashboard/qr-codes/{qrCode}/download/{format}', [QrCodeAssetController::class, 'download'])
        ->name('qr-codes.download');
    Route::get('dashboard/qr-codes/{qrCode}/insights', [QrCodeInsightsController::class, 'campaign'])
        ->name('qr-codes.campaign-insights');
    Route::get('account-settings/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::patch('account-settings/billing/plan', [BillingController::class, 'selectFree'])->name('billing.plan.update');
    Route::get('billing/checkout/{plan:code}/{interval}', CheckoutController::class)
        ->whereIn('interval', ['month', 'year'])
        ->name('billing.checkout');
    Route::get('billing/checkout/success', [CheckoutResultController::class, 'success'])
        ->name('billing.checkout.success');
    Route::get('billing/checkout/cancel', [CheckoutResultController::class, 'cancel'])
        ->name('billing.checkout.cancel');
    Route::get('billing/portal', PortalController::class)->name('billing.portal');
    Route::get('dashboard/custom-domains', [CustomDomainController::class, 'index'])->name('custom-domains.index');
    Route::get('dashboard/insights/custom-events', [AnalyticsEventDefinitionController::class, 'index'])
        ->name('analytics.custom-events.index');
    Route::post('dashboard/insights/custom-events', [AnalyticsEventDefinitionController::class, 'store'])
        ->name('analytics.custom-events.store');
    Route::patch('dashboard/insights/custom-events/{definition}/disable', [AnalyticsEventDefinitionController::class, 'disable'])
        ->name('analytics.custom-events.disable');
    Route::get('dashboard/insights/events', AnalyticsEventController::class)->name('analytics.events');
    Route::get('dashboard/insights/export', AnalyticsExportController::class)->name('analytics.export');
    Route::get('dashboard/insights', AccountAnalyticsController::class)->name('analytics.index');

    Route::get('account-settings/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('account-settings/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::delete('account-settings/api-keys/{token}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

    Route::get('account-settings/webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
    Route::post('account-settings/webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
    Route::patch('account-settings/webhooks/{webhook}', [WebhookController::class, 'update'])->name('webhooks.update');
    Route::post('account-settings/webhooks/{webhook}/rotate', [WebhookController::class, 'rotate'])->name('webhooks.rotate');
    Route::delete('account-settings/webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
    Route::get('account-settings/webhooks/{webhook}/deliveries', [WebhookDeliveryController::class, 'index'])
        ->name('webhooks.deliveries.index');
    Route::post('account-settings/webhooks/{webhook}/deliveries/{delivery}/redeliver', [WebhookDeliveryController::class, 'redeliver'])
        ->name('webhooks.deliveries.redeliver');
    Route::get('dashboard/pixels', [TrackingPixelController::class, 'index'])->name('tracking-pixels.index');
    Route::post('dashboard/pixels', [TrackingPixelController::class, 'store'])->name('tracking-pixels.store');
    Route::patch('dashboard/pixels/{trackingPixel}', [TrackingPixelController::class, 'update'])
        ->name('tracking-pixels.update');
    Route::delete('dashboard/pixels/{trackingPixel}', [TrackingPixelController::class, 'destroy'])
        ->name('tracking-pixels.destroy');
    Route::post('dashboard/pixels/{trackingPixel}/rules', [TrackingPixelController::class, 'storeRule'])
        ->name('tracking-pixels.rules.store');
    Route::delete('dashboard/pixels/{trackingPixel}/rules/{trackingPixelRule}', [TrackingPixelController::class, 'destroyRule'])
        ->name('tracking-pixels.rules.destroy');
    Route::post('dashboard/custom-domains', [CustomDomainController::class, 'store'])->name('custom-domains.store');
    Route::post('dashboard/custom-domains/{customDomain}/verify', [CustomDomainVerificationController::class, 'verify'])
        ->name('custom-domains.verify');
    Route::post('dashboard/custom-domains/{customDomain}/reissue', [CustomDomainVerificationController::class, 'reissue'])
        ->name('custom-domains.reissue');
    Route::delete('dashboard/custom-domains/{customDomain}', [CustomDomainController::class, 'destroy'])
        ->name('custom-domains.destroy');

    Route::post('dashboard/links', [LinkController::class, 'store'])->name('links.store');

    Route::get('dashboard/links/export', [LinkCsvController::class, 'export'])->name('links.export');
    Route::post('dashboard/links/import', [LinkCsvController::class, 'import'])->name('links.import');
    Route::patch('dashboard/links/bulk', [LinkController::class, 'bulkAction'])->name('links.bulk-action');
    Route::get('dashboard/links/{link}/revisions', [LinkRevisionController::class, 'index'])
        ->name('links.revisions.index');
    Route::post('dashboard/links/{link}/revisions/{revision}/approve', [LinkRevisionController::class, 'approve'])
        ->name('links.revisions.approve');
    Route::post('dashboard/links/{link}/revisions/{revision}/reject', [LinkRevisionController::class, 'reject'])
        ->name('links.revisions.reject');
    Route::post('dashboard/link-tags', [LinkTagController::class, 'store'])->name('link-tags.store');
    Route::get('dashboard/links/{link}/insights', LinkInsightsController::class)->name('links.insights');
    Route::get('dashboard/links/{link}/qr-code.svg', LinkQrCodeController::class)->name('links.qr-code');
    Route::patch('dashboard/links/{link}', [LinkController::class, 'update'])->name('links.update');
    Route::get('dashboard/folders', [FolderController::class, 'index'])->name('folders.index');
    Route::post('dashboard/folders', [FolderController::class, 'store'])->name('folders.store');
    Route::delete('dashboard/folders', [FolderController::class, 'bulkDestroy'])->name('folders.bulk-destroy');
    Route::patch('dashboard/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
    Route::delete('dashboard/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
    Route::delete('dashboard/links/{link}', [LinkController::class, 'destroy'])->name('links.destroy');

    Route::get('dashboard/biolinks', [BioPageController::class, 'index'])->name('bio-pages.index');
    Route::post('dashboard/biolinks', [BioPageController::class, 'store'])->name('bio-pages.store');
    Route::get('dashboard/biolinks/{bioPage}', [BioPageController::class, 'show'])->name('bio-pages.show');
    Route::get('dashboard/biolinks/{bioPage}/insights', BioPageInsightsController::class)
        ->name('bio-pages.insights');
    Route::patch('dashboard/biolinks/{bioPage}', [BioPageController::class, 'update'])->name('bio-pages.update');
    Route::delete('dashboard/biolinks/{bioPage}', [BioPageController::class, 'destroy'])->name('bio-pages.destroy');
    Route::post('dashboard/biolinks/{bioPage}/links', [BioLinkController::class, 'store'])->name('bio-pages.links.store');
    Route::patch('dashboard/biolinks/{bioPage}/content/reorder', [BioContentBlockController::class, 'reorder'])->name('bio-pages.content.reorder');
    Route::patch('dashboard/biolinks/{bioPage}/links/{bioLink}', [BioLinkController::class, 'update'])->name('bio-pages.links.update');
    Route::delete('dashboard/biolinks/{bioPage}/links/{bioLink}', [BioLinkController::class, 'destroy'])->name('bio-pages.links.destroy');
    Route::post('dashboard/biolinks/{bioPage}/blocks', [BioContentBlockController::class, 'store'])->name('bio-pages.blocks.store');
    Route::patch('dashboard/biolinks/{bioPage}/blocks/{bioBlock}', [BioContentBlockController::class, 'update'])->name('bio-pages.blocks.update');
    Route::delete('dashboard/biolinks/{bioPage}/blocks/{bioBlock}', [BioContentBlockController::class, 'destroy'])->name('bio-pages.blocks.destroy');
    Route::get('dashboard/biolinks/{bioPage}/blocks/{bioBlock}/image', BioContentMediaPreviewController::class)
        ->name('bio-pages.blocks.preview-image');

    Route::get('dashboard/link-pages', [LinkPageController::class, 'index'])->name('link-pages.index');
    Route::post('dashboard/link-pages', [LinkPageController::class, 'store'])->name('link-pages.store');
    Route::get('dashboard/link-pages/{linkPage}', [LinkPageController::class, 'show'])->name('link-pages.show');
    Route::get('dashboard/link-pages/{linkPage}/insights', LinkPageInsightsController::class)
        ->name('link-pages.insights');
    Route::post('dashboard/link-pages/{linkPage}/variants', [LinkPageVariantController::class, 'store'])
        ->name('link-pages.variants.store');
    Route::patch('dashboard/link-pages/{linkPage}/variants/{linkPageVariant}', [LinkPageVariantController::class, 'update'])
        ->name('link-pages.variants.update');
    Route::delete('dashboard/link-pages/{linkPage}/variants/{linkPageVariant}', [LinkPageVariantController::class, 'destroy'])
        ->name('link-pages.variants.destroy');
    Route::patch('dashboard/link-pages/{linkPage}', [LinkPageController::class, 'update'])->name('link-pages.update');
    Route::delete('dashboard/link-pages/{linkPage}', [LinkPageController::class, 'destroy'])->name('link-pages.destroy');
    Route::get('dashboard/link-pages/{linkPage}/leads', [LinkPageLeadController::class, 'index'])
        ->name('link-pages.leads.index');
    Route::get('dashboard/link-pages/{linkPage}/leads/export', [LinkPageLeadController::class, 'export'])
        ->name('link-pages.leads.export');
    Route::post('dashboard/link-pages/{linkPage}/blocks', [LinkPageBlockController::class, 'store'])
        ->name('link-pages.blocks.store');
    Route::patch('dashboard/link-pages/{linkPage}/blocks/{linkPageBlock}', [LinkPageBlockController::class, 'update'])
        ->name('link-pages.blocks.update');
    Route::delete('dashboard/link-pages/{linkPage}/blocks/{linkPageBlock}', [LinkPageBlockController::class, 'destroy'])
        ->name('link-pages.blocks.destroy');
    Route::get('dashboard/link-pages/{linkPage}/blocks/{linkPageBlock}/image', [LinkPageBlockController::class, 'imagePreview'])
        ->name('link-pages.blocks.preview-image');
    Route::post('dashboard/link-pages/{linkPage}/items', [LinkPageItemController::class, 'store'])
        ->name('link-pages.items.store');
    Route::patch('dashboard/link-pages/{linkPage}/items/reorder', [LinkPageItemController::class, 'reorder'])
        ->name('link-pages.items.reorder');
    Route::patch('dashboard/link-pages/{linkPage}/items/{linkPageItem}', [LinkPageItemController::class, 'update'])
        ->name('link-pages.items.update');
    Route::delete('dashboard/link-pages/{linkPage}/items/{linkPageItem}', [LinkPageItemController::class, 'destroy'])
        ->name('link-pages.items.destroy');

    Route::get('dashboard/link-overlays', [LinkOverlayController::class, 'index'])->name('overlays.index');
    Route::post('dashboard/link-overlays', [LinkOverlayController::class, 'store'])->name('overlays.store');
    Route::get('dashboard/link-overlays/{linkOverlay}', [LinkOverlayController::class, 'show'])->name('overlays.show');
    Route::patch('dashboard/link-overlays/{linkOverlay}', [LinkOverlayController::class, 'update'])->name('overlays.update');
    Route::delete('dashboard/link-overlays/{linkOverlay}', [LinkOverlayController::class, 'destroy'])->name('overlays.destroy');

    Route::prefix('admin')->name('admin.')->middleware('platform:admin.access')->group(function (): void {
        Route::get('/', AdminHomeController::class)->name('index');

        Route::get('users', AdminUserIndexController::class)
            ->middleware('platform:admin.users.view')
            ->name('users.index');
        Route::get('users/{user}', AdminUserShowController::class)
            ->middleware('platform:admin.users.view')
            ->name('users.show');
        Route::patch('users/{user}/suspension', [AdminUserActionController::class, 'updateSuspension'])
            ->middleware('platform:admin.users.manage_status')
            ->name('users.suspension.update');
        Route::patch('users/{user}/verification', [AdminUserActionController::class, 'updateVerification'])
            ->middleware('platform:admin.users.manage_verification')
            ->name('users.verification.update');
        Route::post('users/{user}/verification/resend', [AdminUserActionController::class, 'resendVerification'])
            ->middleware('platform:admin.users.manage_verification')
            ->name('users.verification.resend');
        Route::delete('users/{user}/sessions', [AdminUserActionController::class, 'revokeSessions'])
            ->middleware('platform:admin.users.manage_status')
            ->name('users.sessions.destroy');
        Route::delete('users/{user}/tokens', [AdminUserActionController::class, 'revokeTokens'])
            ->middleware('platform:admin.users.manage_status')
            ->name('users.tokens.destroy');
        Route::patch('users/{user}/roles', [AdminUserActionController::class, 'updateRole'])
            ->middleware('platform:admin.users.manage_roles')
            ->name('users.roles.update');
        Route::patch('users/{user}/deletion', [AdminUserActionController::class, 'scheduleDeletion'])
            ->middleware('platform:admin.users.schedule_deletion')
            ->name('users.deletion.schedule');
        Route::patch('users/{user}/deletion/restore', [AdminUserActionController::class, 'restoreDeletion'])
            ->middleware('platform:admin.users.schedule_deletion')
            ->name('users.deletion.restore');
        Route::patch('users/{user}/plan', [AdminUserActionController::class, 'updatePlan'])
            ->middleware('platform:admin.billing.manage')
            ->name('users.plan.update');

        Route::get('insights', [AdminInsightsController::class, 'index'])
            ->middleware('platform:admin.insights.view')
            ->name('insights.index');
        Route::get('insights/visitors', [AdminInsightsController::class, 'visitors'])
            ->middleware('platform:admin.insights.view')
            ->name('insights.visitors');

        Route::get('links', [AdminContentController::class, 'links'])
            ->middleware('platform:admin.content.view_metadata')
            ->name('content.links');
        Route::get('biolinks', [AdminContentController::class, 'bioLinks'])
            ->middleware('platform:admin.content.view_metadata')
            ->name('content.biolinks');
        Route::get('folders', [AdminContentController::class, 'folders'])
            ->middleware('platform:admin.content.view_metadata')
            ->name('content.folders');
        Route::get('custom-domains', [AdminContentController::class, 'domains'])
            ->middleware('platform:admin.content.view_metadata')
            ->name('content.domains');
        Route::get('link-overlays', [AdminContentController::class, 'overlays'])
            ->middleware('platform:admin.content.view_metadata')
            ->name('content.overlays');
        Route::get('link-pages', [AdminContentController::class, 'pages'])
            ->middleware('platform:admin.content.view_metadata')
            ->name('content.pages');
        Route::get('pixels', [AdminContentController::class, 'pixels'])
            ->middleware('platform:admin.content.view_metadata')
            ->name('content.pixels');

        Route::get('files', [AdminFileController::class, 'index'])
            ->middleware('platform:admin.files.view')
            ->name('files.index');
        Route::delete('files/{media}', [AdminFileController::class, 'destroy'])
            ->middleware('platform:admin.files.manage')
            ->name('files.destroy');
        Route::patch('files/{media}/restore', [AdminFileController::class, 'restore'])
            ->middleware('platform:admin.files.manage')
            ->name('files.restore');

        Route::get('custom-pages', [AdminCustomPageController::class, 'index'])
            ->middleware('platform:admin.pages.manage')
            ->name('pages.index');
        Route::get('custom-pages/create', [AdminCustomPageController::class, 'create'])
            ->middleware('platform:admin.pages.manage')
            ->name('pages.create');
        Route::post('custom-pages', [AdminCustomPageController::class, 'store'])
            ->middleware('platform:admin.pages.manage')
            ->name('pages.store');
        Route::get('custom-pages/{customPage}/edit', [AdminCustomPageController::class, 'edit'])
            ->middleware('platform:admin.pages.manage')
            ->name('pages.edit');
        Route::patch('custom-pages/{customPage}', [AdminCustomPageController::class, 'update'])
            ->middleware('platform:admin.pages.manage')
            ->name('pages.update');
        Route::delete('custom-pages/{customPage}', [AdminCustomPageController::class, 'destroy'])
            ->middleware('platform:admin.pages.manage')
            ->name('pages.destroy');
        Route::patch('custom-pages/{customPage}/publish', [AdminCustomPageController::class, 'publish'])
            ->middleware('platform:admin.pages.manage')
            ->name('pages.publish');
        Route::get('custom-pages/{customPage}/preview', [AdminCustomPageController::class, 'preview'])
            ->middleware('platform:admin.pages.manage')
            ->name('pages.preview');

        Route::get('plans', [AdminPlanController::class, 'index'])
            ->middleware('platform:admin.billing.view')
            ->name('plans.index');
        Route::patch('plans/{plan:code}', [AdminPlanController::class, 'update'])
            ->middleware('platform:admin.billing.manage')
            ->name('plans.update');

        Route::get('subscriptions', [AdminSubscriptionController::class, 'index'])
            ->middleware('platform:admin.billing.view')
            ->name('subscriptions.index');
        Route::post('subscriptions/{workspace}/cancel', [AdminSubscriptionController::class, 'cancel'])
            ->middleware('platform:admin.billing.manage')
            ->name('subscriptions.cancel');
        Route::post('subscriptions/{workspace}/resume', [AdminSubscriptionController::class, 'resume'])
            ->middleware('platform:admin.billing.manage')
            ->name('subscriptions.resume');
        Route::post('subscriptions/{workspace}/overrides', [AdminSubscriptionController::class, 'storeOverride'])
            ->middleware('platform:admin.billing.manage')
            ->name('subscriptions.overrides.store');
        Route::patch('subscriptions/{workspace}/overrides/{override}/revoke', [AdminSubscriptionController::class, 'revokeOverride'])
            ->middleware('platform:admin.billing.manage')
            ->name('subscriptions.overrides.revoke');

        Route::get('roles', AdminRoleController::class)
            ->middleware('platform:admin.users.manage_roles')
            ->name('roles.index');

        Route::post('notifications/broadcast', [AdminNotificationController::class, 'store'])
            ->middleware('platform:admin.notifications.broadcast')
            ->name('notifications.broadcast');
    });
});

Route::middleware(['auth', 'active-account'])->group(function (): void {
    Route::get('invitations/{token}', [AcceptWorkspaceInvitationController::class, 'show'])
        ->name('invitations.show');
    Route::post('invitations/{token}/accept', [AcceptWorkspaceInvitationController::class, 'accept'])
        ->name('invitations.accept');
});

require __DIR__.'/settings.php';

Route::post('tracking-consent', TrackingConsentController::class)
    ->name('tracking-consent.store');
Route::get('content/{slug}', PublicCustomPageController::class)
    ->where('slug', '[a-z0-9-]{3,80}')
    ->name('custom-pages.show');

Route::get('content-media/{publicId}', ContentMediaController::class)
    ->whereUuid('publicId')
    ->name('content-media.show');

Route::post('pages/{slug}/blocks/{linkPageBlock}/leads', PublicLinkPageLeadController::class)
    ->where('slug', '[a-z0-9-]{3,32}')
    ->name('link-pages.leads.submit');

Route::get('pages/{slug}/items/{linkPageItem}', PublicLinkPageItemController::class)
    ->where('slug', '[a-z0-9-]{3,32}')
    ->name('link-pages.items.public');

Route::get('pages/{slug}', PublicLinkPageController::class)
    ->where('slug', '[a-z0-9-]{3,32}')
    ->name('link-pages.public');

Route::get('bio/{handle}/blocks/{bioBlock}/image', PublicBioContentMediaController::class)
    ->where('handle', '[a-z0-9-]{3,32}')
    ->name('bio-pages.blocks.image');
Route::get('bio/{handle}/blocks/{bioBlock}', PublicBioContentBlockController::class)
    ->where('handle', '[a-z0-9-]{3,32}')
    ->name('bio-pages.blocks.public');

Route::get('bio/{handle}/links/{bioLink}', PublicBioLinkController::class)
    ->where('handle', '[a-z0-9-]{3,32}')
    ->name('bio-pages.links.public');

Route::get('bio/{handle}', PublicBioPageController::class)
    ->where('handle', '[a-z0-9-]{3,32}')
    ->name('bio-pages.public');
Route::get('q/{code}', [PublicQrCodeController::class, 'show'])
    ->where('code', '[a-z0-9]{12,24}')
    ->name('qr-codes.public');

Route::post('q/{code}/unlock', QrAccessController::class)
    ->where('code', '[a-z0-9]{12,24}')
    ->name('qr-codes.unlock');

Route::get('.well-known/linkpilot-domain/{token}', DomainChallengeController::class)
    ->name('custom-domains.challenge');

Route::post('{slug}/continue', [PublicLinkOverlayController::class, 'continue'])
    ->where('slug', '[a-z0-9-]{4,32}')
    ->name('links.continue');

Route::post('{slug}/unlock', LinkAccessController::class)
    ->where('slug', '[a-z0-9-]{4,32}')
    ->name('links.unlock');

Route::get('{slug}', PublicLinkController::class)
    ->where('slug', '[a-z0-9-]{4,32}')
    ->name('links.redirect');
