<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

/**
 * Deliberately OUTSIDE both the `guest` and `auth` groups — snags #27 and
 * #65, ruled 15 Sep 2026.
 *
 * `auth` turned a click from the user's own inbox into a login demand
 * whenever the link opened in a browser without a session — which, with
 * Outlook opening links in Edge, is the normal case rather than the odd
 * one. Worse, if that browser happened to hold a session for somebody
 * else, the request got past `auth` and died at a 403 (observed on prod
 * 2 Aug 2026), which reads as broken rather than as a step to complete.
 *
 * `guest` would be no better: it would lock out the same-browser click,
 * which is the other half of the acceptance test.
 *
 * The link identifies its owner on its own — `signed` proves it was
 * issued by us and has not expired, and the controller checks the hash
 * against the user's current email — so the session it arrives with is
 * irrelevant. RULED, with the trade-off accepted: possession of the
 * email within its 60-minute life grants a session. Revisit before
 * registration opens to the public.
 */
Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

});

/*
 * #75 (second half) — "forgotten password" is reachable WHILE SIGNED IN.
 *
 * Inside the `guest` group these two bounced an authenticated visitor to the
 * dashboard, which walled off the person the whole feature is for: signed in
 * on a phone, does not know the password, wants a new one. Profile cannot
 * help — it demands the current password. Their only route was to sign out
 * first, and that is precisely what they hesitate to do, because if it goes
 * wrong they are locked out of an account that was working a minute ago.
 *
 * DELIBERATELY NO LOGOUT HERE, unlike the reset routes below. Requesting a
 * link is not the commitment; opening it is. Staying signed in while you go
 * and check your inbox means a link that never arrives costs you nothing.
 */
Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
    ->name('password.request');

Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
    ->name('password.email');

/*
 * #75 — the password-reset routes deliberately sit OUTSIDE the `guest` group.
 *
 * Inside it, an authenticated visitor was redirected to the dashboard and
 * never saw the reset form. That reads as a tester's edge case and is not: a
 * tenant signed in permanently on a phone, who has not typed the password in
 * months, taps the emailed link on that same phone and lands on the dashboard
 * with no explanation — and the profile page then demands the password they
 * are trying to replace. A closed loop with no way out.
 *
 * The controller signs out any existing session before showing the form, so
 * "logged in" is never a state these two routes have to reason about.
 */
Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
    ->name('password.reset');

Route::post('reset-password', [NewPasswordController::class, 'store'])
    ->name('password.store');

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
