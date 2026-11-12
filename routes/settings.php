<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Settings\AccountAvatarController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RestoreAccountController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SessionController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active-account'])->group(function () {
    Route::get('account-settings/avatar/{publicId}', [AccountAvatarController::class, 'show'])->name('account.avatar');

    Route::get('account-settings/general', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('account-settings/general', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('account-settings/restore', [RestoreAccountController::class, 'show'])->name('account.restore');
    Route::post('account-settings/restore', [RestoreAccountController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('account.restore.store');
});

Route::middleware(['auth', 'verified', 'active-account'])->group(function () {
    Route::delete('account-settings/general', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('account-settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('account-settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('account-settings/google/connect', [GoogleAuthController::class, 'connect'])->name('google.connect');
    Route::get('account-settings/google/callback', [GoogleAuthController::class, 'connectCallback'])->name('google.connect.callback');
    Route::delete('account-settings/google', [GoogleAuthController::class, 'disconnect'])->name('google.disconnect');

    Route::delete('account-settings/sessions/others', [SessionController::class, 'destroyOthers'])
        ->middleware(RequirePassword::class)
        ->name('sessions.destroy-others');
    Route::delete('account-settings/api-tokens', [SessionController::class, 'revokeTokens'])
        ->middleware(RequirePassword::class)
        ->name('api-tokens.revoke');

    Route::inertia('account-settings/appearance', 'settings/appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
