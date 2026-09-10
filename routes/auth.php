<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialAuthenticationController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::middleware('registration.enabled')->group(function () {
        Route::get('register', [RegisteredUserController::class, 'create'])->name('register');

        Route::post('register', [RegisteredUserController::class, 'store'])
            ->middleware('throttle:5,1');
    });

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:20,1');

    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.challenge');

    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:two-factor')
        ->name('two-factor.verify');

    Route::get('auth/{provider}/redirect', [SocialAuthenticationController::class, 'redirect'])
        ->middleware('throttle:10,1')
        ->name('social.redirect');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.store');
});

// Fora do grupo "guest" de propósito: o provedor só conhece UMA URL de
// callback por ambiente (registrada no console do Google/GitHub), então ela
// precisa atender tanto o login de visitante quanto o re-consentimento de
// quem já está autenticado. O controller separa os dois casos e continua
// mandando o visitante autenticado sem intenção de reautenticar de volta
// para o dashboard, preservando o comportamento anterior.
Route::get('auth/{provider}/callback', [SocialAuthenticationController::class, 'callback'])
    ->middleware('throttle:10,1')
    ->name('social.callback');

Route::middleware('auth')->group(function () {
    Route::get('auth/{provider}/reauthenticate', [SocialAuthenticationController::class, 'reauthenticate'])
        ->middleware('throttle:10,1')
        ->name('social.reauthenticate');

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::put('password', [PasswordController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
