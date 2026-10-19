<?php

use App\Http\Controllers\LinkController;
use App\Http\Controllers\LinkInsightsController;
use App\Http\Controllers\PublicLinkController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', fn () => to_route('links.index'))->name('dashboard');
    Route::get('links', [LinkController::class, 'index'])->name('links.index');
    Route::post('links', [LinkController::class, 'store'])->name('links.store');
    Route::get('links/{link}/insights', LinkInsightsController::class)->name('links.insights');
    Route::patch('links/{link}', [LinkController::class, 'update'])->name('links.update');
    Route::delete('links/{link}', [LinkController::class, 'destroy'])->name('links.destroy');
});

require __DIR__.'/settings.php';

Route::get('{slug}', PublicLinkController::class)
    ->where('slug', '[a-z0-9-]{4,32}')
    ->name('links.redirect');
