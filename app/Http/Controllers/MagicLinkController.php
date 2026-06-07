<?php

namespace App\Http\Controllers;

use App\Models\MagicLoginToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * D12 — consumes a signed magic link. Three failure modes, all
 * landing on /login (never a dead-end), with a flash hint that
 * doesn't leak token validity to a brute-force attacker:
 *
 *   - Unknown token: redirect to /login, no flash.
 *   - Expired token: redirect to /login with "this link has expired".
 *   - Already-used token: redirect to /login with "already used".
 *
 * Success: stamp used_at, Auth::login the user, redirect to the
 * case page (or /cases if no case attached).
 *
 * Signature validation is handled by the `signed` middleware on the
 * route; by the time consume() runs, the signature has been checked.
 */
class MagicLinkController extends Controller
{
    public function consume(Request $request, string $token): RedirectResponse
    {
        $row = MagicLoginToken::where('token', $token)->first();

        if ($row === null) {
            return redirect()->route('login');
        }

        if ($row->isConsumed()) {
            return redirect()->route('login')
                ->with('error', 'This link has already been used — please sign in.');
        }

        if ($row->isExpired()) {
            return redirect()->route('login')
                ->with('error', 'This link has expired — please sign in.');
        }

        $row->used_at = now();
        $row->save();

        Auth::login($row->user);

        if ($row->case !== null) {
            return redirect()->route('cases.show', $row->case->url_slug);
        }

        return redirect()->route('cases.index');
    }
}
