<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * #64 — show/hide on every password field.
 *
 * Raised by Charlie 15 Sep 2026: a mistyped password is invisible, so on
 * login it cannot be told from a wrong one, and on registration it
 * surfaces only as a mismatch error that does not say which box is wrong.
 *
 * The point of these tests is COVERAGE, not the toggle itself. The
 * behaviour is a few lines of JavaScript; the thing that goes wrong is a
 * field being left out — the create-case dropdown on 4 Sep, and the snag's
 * own warning that six separate edits is how the sixth gets missed. So
 * every page carrying a password field is asserted by name, and a guard
 * test fails if a new one appears anywhere without the script.
 */
it('carries the reveal script on every page with a password field', function (string $path) {
    $this->get($path)->assertOk()->assertSee('Show password', false);
})->with([
    'login' => '/login',
    'register' => '/register',
    'forgot-password reset form' => '/reset-password/fake-token',
]);

it('carries it on the signed-in password pages too', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    // Change password and delete account both live on the profile page.
    $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Show password', false);

    $this->actingAs($user)->get('/confirm-password')->assertOk()->assertSee('Show password', false);
});

/**
 * The regression this snag is really about. If someone adds a seventh
 * password field in a view that does not use the app layout, this fails
 * rather than the field quietly shipping without a reveal.
 */
it('has no password field in a view that misses the shared layout', function () {
    $views = collect(glob(resource_path('views/**/*.blade.php')))
        ->merge(glob(resource_path('views/**/**/*.blade.php')))
        ->unique()
        ->filter(fn ($file) => str_contains(file_get_contents($file), 'type="password"'));

    expect($views)->not->toBeEmpty('Expected to find the password fields — has the markup changed?');

    $orphans = $views->reject(function ($file) {
        $contents = file_get_contents($file);

        // Either it extends the app layout itself, or it is a partial
        // included by something that does.
        return str_contains($contents, "@extends('layouts.app')")
            || str_contains($file, 'partials');
    });

    expect($orphans->map(fn ($f) => basename($f))->values()->all())->toBe([]);
});

/**
 * Validation messages must survive. The button is inserted as a SIBLING
 * of the input rather than wrapping it, because Bootstrap shows the
 * message with `.is-invalid ~ .invalid-feedback` and a wrapper would take
 * the input out of that relationship — killing the error text on login
 * and registration while looking fine.
 */
it('still shows a validation error under a password field', function () {
    $this->post('/login', ['email' => 'nobody@example.com', 'password' => ''])
        ->assertSessionHasErrors('password');
});

it('does not reveal anything by default', function () {
    $html = $this->get('/login')->getContent();

    // The field ships masked; only a deliberate click changes it, and
    // nothing remembers the choice.
    expect($html)->toContain('type="password"');
    expect($html)->toContain("aria-pressed', 'false'");
});
