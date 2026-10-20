<?php

use App\Http\Controllers\AccountAnalyticsController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BioLinkController;
use App\Http\Controllers\BioPageController;
use App\Http\Controllers\CustomDomainController;
use App\Http\Controllers\CustomDomainVerificationController;
use App\Http\Controllers\DomainChallengeController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\LinkAccessController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\LinkInsightsController;
use App\Http\Controllers\LinkPageController;
use App\Http\Controllers\LinkPageItemController;
use App\Http\Controllers\LinkQrCodeController;
use App\Http\Controllers\PublicBioLinkController;
use App\Http\Controllers\PublicBioPageController;
use App\Http\Controllers\PublicLinkController;
use App\Http\Controllers\PublicLinkPageController;
use App\Http\Controllers\PublicLinkPageItemController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\TrackingConsentController;
use App\Http\Controllers\TrackingPixelController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', 'active-account'])->group(function (): void {
    Route::get('dashboard', fn () => to_route('links.index'))->name('dashboard');
    Route::get('links', [LinkController::class, 'index'])->name('links.index');
    Route::get('dashboard/qr-codes', [QrCodeController::class, 'index'])->name('qr-codes.index');
    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::patch('billing/plan', [BillingController::class, 'selectFree'])->name('billing.plan.update');
    Route::get('custom-domains', [CustomDomainController::class, 'index'])->name('custom-domains.index');
    Route::get('analytics', AccountAnalyticsController::class)->name('analytics.index');

    Route::get('tracking-pixels', [TrackingPixelController::class, 'index'])->name('tracking-pixels.index');
    Route::post('tracking-pixels', [TrackingPixelController::class, 'store'])->name('tracking-pixels.store');
    Route::patch('tracking-pixels/{trackingPixel}', [TrackingPixelController::class, 'update'])
        ->name('tracking-pixels.update');
    Route::delete('tracking-pixels/{trackingPixel}', [TrackingPixelController::class, 'destroy'])
        ->name('tracking-pixels.destroy');
    Route::post('custom-domains', [CustomDomainController::class, 'store'])->name('custom-domains.store');
    Route::post('custom-domains/{customDomain}/verify', [CustomDomainVerificationController::class, 'verify'])
        ->name('custom-domains.verify');
    Route::post('custom-domains/{customDomain}/reissue', [CustomDomainVerificationController::class, 'reissue'])
        ->name('custom-domains.reissue');
    Route::delete('custom-domains/{customDomain}', [CustomDomainController::class, 'destroy'])
        ->name('custom-domains.destroy');

    Route::post('links', [LinkController::class, 'store'])->name('links.store');
    Route::get('links/{link}/insights', LinkInsightsController::class)->name('links.insights');
    Route::get('links/{link}/qr-code.svg', LinkQrCodeController::class)->name('links.qr-code');
    Route::patch('links/{link}', [LinkController::class, 'update'])->name('links.update');
    Route::get('dashboard/folders', [FolderController::class, 'index'])->name('folders.index');
    Route::post('dashboard/folders', [FolderController::class, 'store'])->name('folders.store');
    Route::patch('dashboard/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
    Route::delete('dashboard/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
    Route::delete('links/{link}', [LinkController::class, 'destroy'])->name('links.destroy');

    Route::get('bio-pages', [BioPageController::class, 'index'])->name('bio-pages.index');
    Route::post('bio-pages', [BioPageController::class, 'store'])->name('bio-pages.store');
    Route::get('bio-pages/{bioPage}', [BioPageController::class, 'show'])->name('bio-pages.show');
    Route::patch('bio-pages/{bioPage}', [BioPageController::class, 'update'])->name('bio-pages.update');
    Route::delete('bio-pages/{bioPage}', [BioPageController::class, 'destroy'])->name('bio-pages.destroy');
    Route::post('bio-pages/{bioPage}/links', [BioLinkController::class, 'store'])->name('bio-pages.links.store');
    Route::patch('bio-pages/{bioPage}/links/reorder', [BioLinkController::class, 'reorder'])->name('bio-pages.links.reorder');
    Route::patch('bio-pages/{bioPage}/links/{bioLink}', [BioLinkController::class, 'update'])->name('bio-pages.links.update');
    Route::delete('bio-pages/{bioPage}/links/{bioLink}', [BioLinkController::class, 'destroy'])->name('bio-pages.links.destroy');

    Route::get('link-pages', [LinkPageController::class, 'index'])->name('link-pages.index');
    Route::post('link-pages', [LinkPageController::class, 'store'])->name('link-pages.store');
    Route::get('link-pages/{linkPage}', [LinkPageController::class, 'show'])->name('link-pages.show');
    Route::patch('link-pages/{linkPage}', [LinkPageController::class, 'update'])->name('link-pages.update');
    Route::delete('link-pages/{linkPage}', [LinkPageController::class, 'destroy'])->name('link-pages.destroy');
    Route::post('link-pages/{linkPage}/items', [LinkPageItemController::class, 'store'])
        ->name('link-pages.items.store');
    Route::patch('link-pages/{linkPage}/items/reorder', [LinkPageItemController::class, 'reorder'])
        ->name('link-pages.items.reorder');
    Route::patch('link-pages/{linkPage}/items/{linkPageItem}', [LinkPageItemController::class, 'update'])
        ->name('link-pages.items.update');
    Route::delete('link-pages/{linkPage}/items/{linkPageItem}', [LinkPageItemController::class, 'destroy'])
        ->name('link-pages.items.destroy');

    Route::prefix('admin')->name('admin.')->middleware('administrator')->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('index');
        Route::patch('users/{user}/suspension', [AdminUserController::class, 'updateSuspension'])
            ->name('users.suspension.update');
        Route::patch('users/{user}/administrator', [AdminUserController::class, 'updateAdministrator'])
            ->name('users.administrator.update');
        Route::patch('users/{user}/plan', [AdminUserController::class, 'updatePlan'])
            ->name('users.plan.update');
    });
});

require __DIR__.'/settings.php';

Route::post('tracking-consent', TrackingConsentController::class)
    ->name('tracking-consent.store');

Route::get('pages/{slug}/items/{linkPageItem}', PublicLinkPageItemController::class)
    ->where('slug', '[a-z0-9-]{3,32}')
    ->name('link-pages.items.public');

Route::get('pages/{slug}', PublicLinkPageController::class)
    ->where('slug', '[a-z0-9-]{3,32}')
    ->name('link-pages.public');

Route::get('bio/{handle}/links/{bioLink}', PublicBioLinkController::class)
    ->where('handle', '[a-z0-9-]{3,32}')
    ->name('bio-pages.links.public');

Route::get('bio/{handle}', PublicBioPageController::class)
    ->where('handle', '[a-z0-9-]{3,32}')
    ->name('bio-pages.public');

Route::get('.well-known/linkpilot-domain/{token}', DomainChallengeController::class)
    ->name('custom-domains.challenge');

Route::post('{slug}/unlock', LinkAccessController::class)
    ->where('slug', '[a-z0-9-]{4,32}')
    ->name('links.unlock');

Route::get('{slug}', PublicLinkController::class)
    ->where('slug', '[a-z0-9-]{4,32}')
    ->name('links.redirect');
