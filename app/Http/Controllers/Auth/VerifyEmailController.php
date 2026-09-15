<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Verify an email address from the link we posted — snags #27 and #65.
 *
 * The route sits outside both the `auth` and `guest` groups (see the
 * block above it in routes/auth.php), so this runs for a click from any
 * browser, signed in or not, as whoever. That is the point: one code
 * path, one destination, one message, whichever of Charlie's two test
 * routes the user took.
 *
 * What stands in for a session as proof of identity:
 *   - `signed` on the route — we issued this URL and it has not expired
 *     (60 minutes, Laravel's default; nothing overrides it in
 *     config/auth.php);
 *   - the hash below — it is still the address we sent to, so a link
 *     posted to an address the user has since changed is refused.
 *
 * A plain Request, NOT EmailVerificationRequest: that class's authorize()
 * dereferences $this->user(), so it throws for a guest. It was the second
 * of the two reasons the old guest branch here could never run.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = User::findOrFail($request->route('id'));

        // hash_equals, not ===, because this compares a secret-derived
        // value against one supplied in the URL.
        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            abort(403);
        }

        $alreadyVerified = $user->hasVerifiedEmail();

        if (! $alreadyVerified && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        // The link's identity wins over whatever session the browser
        // happened to be carrying. This is what kills the 2 Aug prod 403:
        // a browser signed in as someone else used to fail the request
        // outright; now it simply becomes a session for the link's owner.
        if (Auth::id() !== $user->id) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        // Flashed, NOT hung off a ?verified=1 query string — that was #65.
        // The old code redirected with intended(), which prefers whatever
        // URL the session already had stored and discards the argument it
        // is given, so the query string (and with it the whole
        // confirmation) vanished on the same-browser route. A flash
        // survives wherever the redirect lands.
        return redirect()
            ->route('dashboard')
            ->with('status', $alreadyVerified
                ? 'Your email address was already verified — you are signed in.'
                : 'Welcome — and thanks for registering! Your email address is verified and your account is ready to go.');
    }
}
