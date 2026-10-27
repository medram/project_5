<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active-account'])->group(function () {
    Route::get('account-settings/general', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('account-settings/general', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified', 'active-account'])->group(function () {
    Route::delete('account-settings/general', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('account-settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('account-settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('account-settings/appearance', 'settings/appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
