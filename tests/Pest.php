<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

// Seed the letter_templates + settings rows for every Feature test
// that uses RefreshDatabase. Without this, SendCaseNotice's template
// lookup throws on an empty letter_templates table, and SilenceClock
// reads on settings would silently fall to defaults. Guarded so
// tests not using RefreshDatabase don't fail when the tables don't
// exist yet.
uses()->beforeEach(function () {
    if (\Illuminate\Support\Facades\Schema::hasTable('letter_templates')) {
        $this->seed(\Database\Seeders\LetterTemplateSeeder::class);
    }
    if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
        $this->seed(\Database\Seeders\SettingSeeder::class);
    }
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Raise (or lower) the letter-1 attachment ceiling for a test.
 *
 * SettingSeeder ships attachments.first_notice_max at 1 — deliberately
 * conservative, on deliverability grounds. Tests that exercise the
 * MULTI-attachment path call this rather than dropping to a single file,
 * so the assertion keeps its original strength.
 *
 * Also used to drive the ceiling to 0 (uploads switched off).
 */
function allowPhotoCeiling(int $max): void
{
    \App\Models\Setting::updateOrCreate(
        ['key' => 'attachments.first_notice_max'],
        ['value' => (string) $max],
    );
}
