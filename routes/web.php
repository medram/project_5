<?php

use App\Http\Controllers\BioLinkController;
use App\Http\Controllers\BioPageController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\LinkAccessController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\LinkInsightsController;
use App\Http\Controllers\LinkQrCodeController;
use App\Http\Controllers\PublicBioPageController;
use App\Http\Controllers\PublicLinkController;
use App\Http\Controllers\QrCodeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', fn () => to_route('links.index'))->name('dashboard');
    Route::get('links', [LinkController::class, 'index'])->name('links.index');
    Route::get('qr-codes', [QrCodeController::class, 'index'])->name('qr-codes.index');
    Route::post('links', [LinkController::class, 'store'])->name('links.store');
    Route::get('links/{link}/insights', LinkInsightsController::class)->name('links.insights');
    Route::get('links/{link}/qr-code.svg', LinkQrCodeController::class)->name('links.qr-code');
    Route::patch('links/{link}', [LinkController::class, 'update'])->name('links.update');
    Route::get('folders', [FolderController::class, 'index'])->name('folders.index');
    Route::post('folders', [FolderController::class, 'store'])->name('folders.store');
    Route::patch('folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
    Route::delete('folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
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
});

require __DIR__.'/settings.php';

Route::get('bio/{handle}', PublicBioPageController::class)
    ->where('handle', '[a-z0-9-]{3,32}')
    ->name('bio-pages.public');

Route::post('{slug}/unlock', LinkAccessController::class)
    ->where('slug', '[a-z0-9-]{4,32}')
    ->name('links.unlock');

Route::get('{slug}', PublicLinkController::class)
    ->where('slug', '[a-z0-9-]{4,32}')
    ->name('links.redirect');
