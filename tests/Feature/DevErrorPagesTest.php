<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * #57 — the error pages, on demand.
 *
 * Three of the four shipped unseen: 419 needs an expired session, 413
 * needs JavaScript disabled now the create form refuses an over-budget
 * selection first, and 500 has no natural trigger. A page whose whole job
 * is to reassure someone at a bad moment should not be the one page
 * nobody has read.
 *
 * The env gate is checked at RUNTIME rather than at route registration,
 * so it can be tested. A gate nobody has exercised is a claim, not a
 * control — and this one is the only thing keeping the route off
 * production.
 */
function asEnvironment(string $env): void
{
    app()->detectEnvironment(fn () => $env);
}

it('renders each error page with its real status code', function (string $code) {
    asEnvironment('local');

    $this->get('/dev/errors/'.$code)->assertStatus((int) $code);
})->with(['404', '413', '419', '500']);

it('lists them all on an index', function () {
    asEnvironment('local');

    $this->get('/dev/errors')
        ->assertOk()
        ->assertSee('Error pages')
        ->assertSee('/dev/errors/419');
});

it('refuses a code that is not one of the four', function () {
    asEnvironment('local');

    // view("errors.$code") on unchecked input would render whatever a
    // caller names, so the list is a whitelist and not a convenience.
    $this->get('/dev/errors/403')->assertNotFound();
    $this->get('/dev/errors/layouts.app')->assertNotFound();
});

it('is refused on production', function () {
    asEnvironment('production');

    $this->get('/dev/errors/500')->assertNotFound();
    $this->get('/dev/errors')->assertNotFound();
});

it('is available on staging, where the pages actually need walking', function () {
    asEnvironment('staging');

    $this->get('/dev/errors/419')->assertStatus(419);
});
