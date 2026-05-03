<?php

use App\Models\ReplyToken;
use App\Services\ReplyTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(function () {
    Str::createRandomStringsNormally();
});

it('generates a 20-character base62 token', function () {
    $token = (new ReplyTokenGenerator)->generate();

    expect(strlen($token))->toBe(20);
    expect($token)->toMatch('/^[A-Za-z0-9]{20}$/');
});

it('returns 100 distinct tokens across consecutive generate() calls', function () {
    $generator = new ReplyTokenGenerator;
    $tokens = [];
    for ($i = 0; $i < 100; $i++) {
        $tokens[] = $generator->generate();
    }

    expect(array_unique($tokens))->toHaveCount(100);
});

it('retries on collision with an existing token in the database', function () {
    // Pre-create the existing row BEFORE faking Str::random — the
    // factory cascades into RepairCase::factory which calls
    // Str::random for url_slug, and we don't want those calls
    // consuming items from our test sequence.
    $existing = ReplyToken::factory()->create();

    $sequence = [$existing->token, 'unique_token_yyyyyyy'];
    Str::createRandomStringsUsing(function () use (&$sequence) {
        return array_shift($sequence);
    });

    $generator = new ReplyTokenGenerator;

    expect($generator->generate())->toBe('unique_token_yyyyyyy');
});

it('throws RuntimeException after exhausting MAX_RETRIES', function () {
    $existing = ReplyToken::factory()->create();

    Str::createRandomStringsUsing(fn () => $existing->token);

    (new ReplyTokenGenerator)->generate();
})->throws(RuntimeException::class);
