<?php

use App\Models\MagicLoginToken;
use App\Models\RepairCase;
use App\Models\User;
use App\Services\MagicLinkGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mints a 64-char single-use token with 7-day expiry', function () {
    $user = User::factory()->create();
    $case = RepairCase::factory()->create(['tenant_user_id' => $user->id]);

    $token = (new MagicLinkGenerator)->mint($user, $case, 'case_reply');

    expect($token->user_id)->toBe($user->id);
    expect($token->case_id)->toBe($case->id);
    expect($token->purpose)->toBe('case_reply');
    expect(strlen($token->token))->toBe(64);
    expect($token->expires_at->greaterThan(now()->addDays(6)))->toBeTrue();
    expect($token->expires_at->lessThanOrEqualTo(now()->addDays(7)))->toBeTrue();
    expect($token->used_at)->toBeNull();
});

it('builds a signed URL via temporarySignedRoute', function () {
    $user = User::factory()->create();
    $case = RepairCase::factory()->create(['tenant_user_id' => $user->id]);

    $url = (new MagicLinkGenerator)->mintUrl($user, $case, 'case_reply');

    expect($url)->toContain('/magic-link/');
    expect($url)->toContain('signature=');
    expect($url)->toContain('expires=');
});

it('consumes a valid link, stamps used_at, and redirects to the case', function () {
    $user = User::factory()->create();
    $case = RepairCase::factory()->create(['tenant_user_id' => $user->id]);
    $url = (new MagicLinkGenerator)->mintUrl($user, $case, 'case_reply');

    $response = $this->get($url);

    $response->assertRedirect("/cases/{$case->url_slug}");
    expect(MagicLoginToken::sole()->used_at)->not->toBeNull();
    expect(auth()->user()?->id)->toBe($user->id);
});

it('rejects a reused link, redirects to /login with a flash error', function () {
    $user = User::factory()->create();
    $case = RepairCase::factory()->create(['tenant_user_id' => $user->id]);
    $url = (new MagicLinkGenerator)->mintUrl($user, $case, 'case_reply');

    $this->get($url);
    auth()->logout();
    $response = $this->get($url);

    $response->assertRedirect('/login');
    $response->assertSessionHas('error');
});

it('rejects an expired token', function () {
    $user = User::factory()->create();
    $case = RepairCase::factory()->create(['tenant_user_id' => $user->id]);
    $url = (new MagicLinkGenerator)->mintUrl($user, $case, 'case_reply');

    // Manually expire the token row (signature still valid; DB expiry past).
    MagicLoginToken::sole()->update(['expires_at' => now()->subDay()]);

    $response = $this->get($url);

    $response->assertRedirect('/login');
    $response->assertSessionHas('error');
});
