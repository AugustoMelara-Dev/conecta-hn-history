<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\AuthenticatedSessionInventoryController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\TenantInvitationController;
use App\Http\Controllers\Auth\TenantInvitationRegistrationController;
use App\Http\Controllers\Auth\TwoFactorSettingsController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;

Route::middleware('guest')->group(function () {
    Route::get('tenant-invitations/{token}/register', [TenantInvitationRegistrationController::class, 'create'])
        ->middleware('throttle:20,1')
        ->name('tenant-invitations.register');

    Route::post('tenant-invitations/{token}/register', [TenantInvitationRegistrationController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('tenant-invitations.register.store');

    Route::redirect('register', '/registrar-negocio', 302)
        ->name('register');

    Route::post('register', fn () => redirect('/registrar-negocio', 307));

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    Route::get('two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'create'])
        ->name('two-factor.login');

    Route::post('two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:two-factor')
        ->name('two-factor.login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('tenant-invitations/{token}', [TenantInvitationController::class, 'show'])
        ->middleware('throttle:20,1')
        ->name('tenant-invitations.show');

    Route::post('tenant-invitations/{token}', [TenantInvitationController::class, 'accept'])
        ->middleware('throttle:10,1')
        ->name('tenant-invitations.accept');

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('/profile/sessions', [AuthenticatedSessionInventoryController::class, 'index'])
        ->name('profile.sessions.index');

    Route::middleware('password.confirm')->group(function () {
        Route::delete('/profile/sessions/others', [AuthenticatedSessionInventoryController::class, 'destroyOthers'])
            ->name('profile.sessions.destroy-others');
        Route::delete('/profile/sessions/{session}', [AuthenticatedSessionInventoryController::class, 'destroy'])
            ->name('profile.sessions.destroy');
        Route::get('/profile/two-factor', [TwoFactorSettingsController::class, 'show'])
            ->name('profile.two-factor.show');
        Route::post('/profile/two-factor', [TwoFactorSettingsController::class, 'enable'])
            ->name('profile.two-factor.enable');
        Route::post('/profile/two-factor/confirm', [TwoFactorSettingsController::class, 'confirm'])
            ->name('profile.two-factor.confirm');
        Route::post('/profile/two-factor/recovery-codes', [TwoFactorSettingsController::class, 'regenerate'])
            ->name('profile.two-factor.recovery-codes');
        Route::delete('/profile/two-factor', [TwoFactorSettingsController::class, 'destroy'])
            ->name('profile.two-factor.destroy');
    });
    // Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/export', [ProfileController::class, 'export'])->middleware('password.confirm')->name('profile.export');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
