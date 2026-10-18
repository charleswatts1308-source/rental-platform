<?php

use App\Mail\Notifications\WelcomeRegistered;
use App\Models\LetterTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/*
 * #81 — "Thanks for registering" email.
 *
 * Sent once, on verification (not on registration), from the
 * welcome_registered template row, and never as a case_messages row.
 * The row reaches long-lived boxes by data migration and fresh builds by
 * the seeder; both are asserted.
 */

function welcomeVerificationUrl(User $user): string
{
    return URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
    );
}

beforeEach(function () {
    Mail::fake();
});

test('the seeder carries the welcome template, active, as account mail', function () {
    $row = LetterTemplate::where('code', 'welcome_registered')->first();

    expect($row)->not->toBeNull();
    expect($row->type)->toBe('account');
    expect($row->active)->toBeTrue();
    expect($row->body)->toContain('You can start a repair case at any time');
});

test('the data migration inserts the welcome template on a box that lacks it', function () {
    LetterTemplate::where('code', 'welcome_registered')->delete();

    $migration = require database_path('migrations/2026_10_18_120000_seed_welcome_registered_template.php');
    $migration->up();

    $row = LetterTemplate::where('code', 'welcome_registered')->first();
    expect($row)->not->toBeNull();
    expect($row->type)->toBe('account');
    expect($row->active)->toBeTrue();
    expect($row->subject)->toBe('Thanks for registering with renters.rent');
});

test('the data migration leaves an edited welcome template alone', function () {
    LetterTemplate::where('code', 'welcome_registered')->update(['subject' => 'Edited on the box']);

    $migration = require database_path('migrations/2026_10_18_120000_seed_welcome_registered_template.php');
    $migration->up();

    expect(LetterTemplate::where('code', 'welcome_registered')->count())->toBe(1);
    expect(LetterTemplate::where('code', 'welcome_registered')->value('subject'))->toBe('Edited on the box');
});

test('verifying sends the welcome email once, rendered for that user', function () {
    $user = User::factory()->unverified()->create(['name' => 'Jo Tenant']);

    $this->get(welcomeVerificationUrl($user));

    Mail::assertQueued(WelcomeRegistered::class, 1);
    Mail::assertQueued(WelcomeRegistered::class, function (WelcomeRegistered $mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->renderedSubject === 'Thanks for registering with renters.rent'
            && str_contains($mail->renderedBody, 'Hello Jo Tenant,')
            && str_contains($mail->renderedBody, 'href="'.route('cases.create').'"')
            && str_contains($mail->renderedBody, '>Start a repair case</a>')
            && ! str_contains($mail->renderedBody, '{{');
    });
});

test('the welcome email carries no case header block', function () {
    $user = User::factory()->unverified()->create();

    $this->get(welcomeVerificationUrl($user));

    Mail::assertQueued(WelcomeRegistered::class, function (WelcomeRegistered $mail) {
        return ! str_contains($mail->renderedBody, 'Case reference:')
            && ! str_contains($mail->renderedBody, 'Property:');
    });
});

test('registering alone sends no welcome email', function () {
    config(['app.registration_open_to_all' => true]);

    $this->post('/register', [
        'name' => 'New Person',
        'email' => 'new.person@example.com',
        'password' => 'password-123456',
        'password_confirmation' => 'password-123456',
    ]);

    // Guard against a vacuous pass: registration must actually have happened.
    expect(User::where('email', 'new.person@example.com')->exists())->toBeTrue();
    Mail::assertNotQueued(WelcomeRegistered::class);
});

test('an already-verified link sends no second welcome email', function () {
    $user = User::factory()->create();

    $this->get(welcomeVerificationUrl($user));

    Mail::assertNotQueued(WelcomeRegistered::class);
});

test('an inactive welcome template sends nothing and verification still succeeds', function () {
    LetterTemplate::where('code', 'welcome_registered')->update(['active' => false]);
    $user = User::factory()->unverified()->create();

    $this->get(welcomeVerificationUrl($user))->assertRedirect(route('cases.index'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Mail::assertNotQueued(WelcomeRegistered::class);
});

test('the welcome email writes no case_messages row', function () {
    $user = User::factory()->unverified()->create();

    $this->get(welcomeVerificationUrl($user));

    Mail::assertQueued(WelcomeRegistered::class);
    $this->assertDatabaseCount('case_messages', 0);
});
