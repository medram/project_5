<?php

use App\Http\Controllers\Api\V1\AnalyticsEventController;
use App\Http\Controllers\Api\V1\AnalyticsExportController;
use App\Http\Controllers\Api\V1\BioPageController;
use App\Http\Controllers\Api\V1\LinkController;
use App\Http\Controllers\Api\V1\LinkPageController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Middleware\AuditApiRequest;
use App\Http\Middleware\EnsureWorkspaceApiToken;
use App\Http\Middleware\HandleApiIdempotency;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'active-account',
    EnsureWorkspaceApiToken::class,
    AuditApiRequest::class,
    'throttle:api-v1',
])->prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('links', [LinkController::class, 'index'])->middleware('abilities:links:read')->name('links.index');
    Route::post('links', [LinkController::class, 'store'])->middleware(['abilities:links:write', HandleApiIdempotency::class])->name('links.store');
    Route::get('links/{link}', [LinkController::class, 'show'])->whereNumber('link')->middleware('abilities:links:read')->name('links.show');
    Route::patch('links/{link}', [LinkController::class, 'update'])->whereNumber('link')->middleware('abilities:links:write')->name('links.update');
    Route::delete('links/{link}', [LinkController::class, 'destroy'])->whereNumber('link')->middleware('abilities:links:write')->name('links.destroy');

    Route::get('bio-pages', [BioPageController::class, 'index'])->middleware('abilities:bio-pages:read')->name('bio-pages.index');
    Route::post('bio-pages', [BioPageController::class, 'store'])->middleware(['abilities:bio-pages:write', HandleApiIdempotency::class])->name('bio-pages.store');
    Route::get('bio-pages/{bioPage}', [BioPageController::class, 'show'])->whereNumber('bioPage')->middleware('abilities:bio-pages:read')->name('bio-pages.show');
    Route::patch('bio-pages/{bioPage}', [BioPageController::class, 'update'])->whereNumber('bioPage')->middleware('abilities:bio-pages:write')->name('bio-pages.update');
    Route::delete('bio-pages/{bioPage}', [BioPageController::class, 'destroy'])->whereNumber('bioPage')->middleware('abilities:bio-pages:write')->name('bio-pages.destroy');

    Route::get('link-pages', [LinkPageController::class, 'index'])->middleware('abilities:link-pages:read')->name('link-pages.index');
    Route::post('link-pages', [LinkPageController::class, 'store'])->middleware(['abilities:link-pages:write', HandleApiIdempotency::class])->name('link-pages.store');
    Route::get('link-pages/{linkPage}', [LinkPageController::class, 'show'])->whereNumber('linkPage')->middleware('abilities:link-pages:read')->name('link-pages.show');
    Route::patch('link-pages/{linkPage}', [LinkPageController::class, 'update'])->whereNumber('linkPage')->middleware('abilities:link-pages:write')->name('link-pages.update');
    Route::delete('link-pages/{linkPage}', [LinkPageController::class, 'destroy'])->whereNumber('linkPage')->middleware('abilities:link-pages:write')->name('link-pages.destroy');

    Route::get('analytics/exports', AnalyticsExportController::class)->middleware('abilities:analytics:read')->name('analytics.exportCsv');

    Route::get('webhooks', [WebhookController::class, 'index'])->middleware('abilities:webhooks:read')->name('webhooks.index');
    Route::post('webhooks', [WebhookController::class, 'store'])->middleware(['abilities:webhooks:write', HandleApiIdempotency::class])->name('webhooks.store');
    Route::get('webhooks/{webhook}', [WebhookController::class, 'show'])->whereNumber('webhook')->middleware('abilities:webhooks:read')->name('webhooks.show');
    Route::patch('webhooks/{webhook}', [WebhookController::class, 'update'])->whereNumber('webhook')->middleware('abilities:webhooks:write')->name('webhooks.update');
    Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->whereNumber('webhook')->middleware('abilities:webhooks:write')->name('webhooks.destroy');

    Route::post('events', [AnalyticsEventController::class, 'store'])->middleware(['abilities:events:write', HandleApiIdempotency::class])->name('events.store');
});
