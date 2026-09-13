<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * #57 — there was no errors/ directory at all, so every HTTP error on
 * production rendered the framework's bare page: correct status, correct
 * behaviour, no reassurance and no way back. On a product whose whole
 * proposition is that it reliably handles something that matters, that is
 * a trust surface.
 *
 * WHAT IS TESTABLE, AND WHAT IS NOT. The suite cannot trigger a real 413:
 * post_max_size is a server directive and PHP refuses the body before any
 * application code runs. Same category as #43 — the mechanism lives below
 * where tests reach. What IS testable is that each view renders, renders
 * STANDALONE with no session and no authenticated user, and makes no claim
 * the system cannot honour.
 */

it('#57 — renders a 404 page for an unknown address', function () {
    $response = $this->get('/no-such-page-exists-here');

    $response->assertNotFound();
    $response->assertSee('We could not find that page');
    $response->assertSee('Your cases');
});

it('#57 — the 413 view renders with NO session and NO authenticated user', function () {
    // This is the constraint that shapes the whole page. ValidatePostSize
    // is global middleware and runs before the web group's StartSession, so
    // when a real 413 renders there is no session to read. Rendering the
    // view directly is the closest the suite can get to that condition.
    $html = view('errors.413')->render();

    expect($html)->toContain('That was too large to send');
    expect($html)->toContain('nothing has been saved');
});

it('#57 — the 413 page does NOT show sign-in chrome', function () {
    // It cannot verify sign-in status without a session, so it must not
    // assert it either way. Telling a tenant who just lost their work that
    // they are also signed out would be false: the cookie is untouched.
    $html = view('errors.413')->render();

    expect($html)->not->toContain('Register');
    expect($html)->not->toContain('Log in');
    expect($html)->not->toContain('Logout');
});

it('#57 — the 413 page RENDERS the size limits rather than hardcoding them', function () {
    $html = view('errors.413')->render();

    // The figures must track PHP's own configuration. A hardcoded number
    // here would drift the moment the hosting panel changed, which has
    // already happened once mid-project (#58).
    expect($html)->toContain(\App\Support\PhotoLimits::perFileLabel());
    expect($html)->toContain(\App\Support\PhotoLimits::totalLabel());
});

it('#57 — no error page claims a draft was saved', function () {
    // The standing rule from #46, #49 and #53: no surface may claim what
    // the system cannot deliver. Nothing is saved when any of these render,
    // and an error page is the worst possible place to be optimistic.
    foreach (['404', '413', '419', '500'] as $code) {
        $html = strtolower(view('errors.'.$code)->render());

        expect($html)->not->toContain('we have saved');
        expect($html)->not->toContain('your draft has been saved');
        expect($html)->not->toContain('your work has been saved');
        expect($html)->not->toContain("don't worry");
    }
});

it('#57 — every error page offers a route back', function () {
    foreach (['404', '413', '419', '500'] as $code) {
        $html = view('errors.'.$code)->render();

        expect($html)->toContain('<a href=');
        expect($html)->toContain('btn btn-primary');
    }
});

it('#57 — the 419 page tells the tenant to copy their text before signing back in', function () {
    // Session expiry on a long form is the most likely error a real tenant
    // meets, because writing up a repair honestly takes time.
    $html = view('errors.419')->render();

    expect($html)->toContain('Your session expired');
    expect($html)->toContain('nothing has been saved');
    expect($html)->toContain('copying it');
});

it('#57 — the 500 page does not guess whether the work completed', function () {
    $html = view('errors.500')->render();

    expect($html)->toContain('cannot tell from here');
    // And it does not promise anybody has been alerted.
    expect(strtolower($html))->not->toContain('our team has been notified');
});
