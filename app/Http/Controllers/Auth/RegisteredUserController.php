<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // The real gate. Open-to-all → anyone; otherwise (beta) → only
        // allowlisted emails. A crafted POST hits exactly this check.
        if (! $this->registrationAllowed($request->email)) {
            throw ValidationException::withMessages([
                'email' => 'renters.rent is in a private beta — registration is invite-only for now. If you think your email should have access, please get in touch.',
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * Decide whether the submitted email may register.
     *
     * Two independent controls:
     *   - registration_open_to_all (bool) — the beta on/off switch. True →
     *     anyone may register; the allowlist is ignored entirely.
     *   - registration_allowlist (csv) — who may register DURING beta only.
     *
     * Editing the allowlist NEVER changes the open/closed state. In beta an
     * empty allowlist simply admits nobody — the site stays in beta. Only
     * the boolean flips beta off.
     */
    private function registrationAllowed(string $email): bool
    {
        if (config('app.registration_open_to_all')) {
            return true;
        }

        $submitted = strtolower(trim($email));

        $allowlist = collect(explode(',', (string) config('app.registration_allowlist')))
            ->map(fn ($entry) => strtolower(trim($entry)))
            ->filter()
            ->all();

        return in_array($submitted, $allowlist, true);
    }
}
