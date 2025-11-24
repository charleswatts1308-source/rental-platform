<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        // If user is already logged in, use existing logic
        if (Auth::check()) {
            if ($request->user()->hasVerifiedEmail()) {
                return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
            }

            if ($request->user()->markEmailAsVerified()) {
                event(new Verified($request->user()));
            }

            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        // If user is not logged in, verify the link and log them in
        $userId = $request->route('id');
        $user = User::findOrFail($userId);

        // Verify the URL signature is valid for this user
        if (! URL::hasValidSignature($request)) {
            abort(403);
        }

        // Check if email is already verified
        if ($user->hasVerifiedEmail()) {
            Auth::login($user);
            return redirect()->route('dashboard')->with('status', 'Email already verified!');
        }

        // Mark email as verified
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        // Log the user in
        Auth::login($user);

        return redirect()->route('dashboard')->with('status', 'Email verified successfully! Welcome to Renters.');
    }
}
