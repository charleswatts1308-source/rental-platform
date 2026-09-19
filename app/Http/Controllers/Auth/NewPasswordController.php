<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     *
     * #75 — these two routes used to sit inside the `guest` middleware group,
     * which bounces an authenticated visitor to the dashboard. The realistic
     * victim is not a tester: it is a tenant who stays permanently signed in
     * on a phone, never types the password for six months, then needs it.
     * They request a reset, tap the link on that same phone, land on the
     * dashboard with no explanation, and find the profile page asking for the
     * very password they have forgotten. No way through, and nothing telling
     * them to sign out first.
     *
     * So the reset routes now accept a signed-in visitor, and END THE SESSION
     * before showing the form. Clicking a reset link is an unambiguous "I want
     * new credentials"; signing the old session out is the honest answer to
     * it, and it leaves no room for the confusing half-state where you are
     * logged in as one account while resetting another.
     */
    public function create(Request $request): View
    {
        $this->endAnyExistingSession($request);

        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Sign out whoever is currently logged in, if anyone.
     *
     * Invalidating and regenerating matters: the reset form that follows needs
     * a CSRF token belonging to the new session, or the POST fails with a 419
     * and the user is back where they started.
     */
    private function endAnyExistingSession(Request $request): void
    {
        if (! Auth::check()) {
            return;
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // #75 — a signed-in visitor can reach this route now, so end that
        // session before resetting. Without it a user could reset account B's
        // password while holding account A's session, which is a confusing
        // state to leave anyone in even though the reset itself is sound.
        $this->endAnyExistingSession($request);

        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
