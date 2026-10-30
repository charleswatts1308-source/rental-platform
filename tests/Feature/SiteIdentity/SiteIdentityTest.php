<?php

use App\Support\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Site identity (docs/cc-report-site-identity-d0.md).
 *
 * The service's name and domain are settings with no defaults, read only
 * through App\Support\Site. phpunit.xml sets deliberately fake values, so
 * any page or mail that still spells out a real brand shows up as a
 * failure elsewhere in the suite.
 */

it('reads the name and domain from config', function () {
    expect(Site::name())->toBe('Test Site Name')
        ->and(Site::domain())->toBe('site-domain.test');
});

it('refuses a blank name rather than guessing one', function () {
    config(['site.name' => '']);

    Site::name();
})->throws(RuntimeException::class, 'SITE_NAME is not configured');

it('refuses a blank domain rather than guessing one', function () {
    config(['site.domain' => '   ']);

    Site::domain();
})->throws(RuntimeException::class, 'SITE_DOMAIN is not configured');

/*
 * THE HOST BADGE. Charlie, 25 Oct 2026: "vital for knowing which site is
 * being tested". It must show the box's REAL host, never SITE_DOMAIN: on
 * gafol SITE_DOMAIN is the brand domain, and a badge reading it would stop
 * telling staging from production.
 */

it('shows the real request host in the badge, not SITE_DOMAIN, outside production', function () {
    config(['site.domain' => 'brand-domain.test']);

    $this->get('http://staging-box.test/')
        ->assertOk()
        ->assertSee('<span class="badge bg-warning text-dark ms-2 align-self-center text-lowercase">staging-box.test</span>', false)
        ->assertDontSee('text-lowercase">brand-domain.test</span>', false);
});

it('shows no host badge in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('http://prod-box.test/')
        ->assertOk()
        ->assertDontSee('bg-warning text-dark ms-2 align-self-center text-lowercase', false);
});
