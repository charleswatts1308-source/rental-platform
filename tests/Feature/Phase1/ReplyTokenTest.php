<?php

use App\Models\RepairCase;
use App\Models\ReplyToken;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('creates a valid reply token via factory', function () {
    $token = ReplyToken::factory()->create();

    expect($token->id)->toBeInt();
    expect(strlen($token->token))->toBe(20);
    expect($token->bound_email)->toBeString();
    expect($token->use_count)->toBe(0);
    expect($token->superseded_at)->toBeNull();
});

it('casts datetimes to Carbon instances', function () {
    $token = ReplyToken::factory()->create([
        'expires_at' => now()->addDays(30),
        'superseded_at' => now()->subDay(),
        'last_used_at' => now(),
    ]);

    expect($token->issued_at)->toBeInstanceOf(Carbon::class);
    expect($token->expires_at)->toBeInstanceOf(Carbon::class);
    expect($token->superseded_at)->toBeInstanceOf(Carbon::class);
    expect($token->last_used_at)->toBeInstanceOf(Carbon::class);
});

it('casts use_count to integer', function () {
    $token = ReplyToken::factory()->create(['use_count' => 5]);

    expect($token->use_count)->toBe(5)->toBeInt();
});

it('produces a superseded token via the superseded state', function () {
    $token = ReplyToken::factory()->superseded()->create();

    expect($token->superseded_at)->toBeInstanceOf(Carbon::class);
    expect($token->expires_at)->toBeInstanceOf(Carbon::class);
});

it('belongs to a repair case', function () {
    $case = RepairCase::factory()->create();
    $token = ReplyToken::factory()->create(['case_id' => $case->id]);

    expect($token->case)->toBeInstanceOf(RepairCase::class);
    expect($token->case->id)->toBe($case->id);
});

it('exposes a replyTokens hasMany relationship on RepairCase', function () {
    $case = RepairCase::factory()->create();
    ReplyToken::factory()->count(2)->create(['case_id' => $case->id]);

    expect($case->replyTokens)->toHaveCount(2);
    expect($case->replyTokens->first())->toBeInstanceOf(ReplyToken::class);
});

it('cascades deletes when the parent case is deleted', function () {
    $case = RepairCase::factory()->create();
    ReplyToken::factory()->count(2)->create(['case_id' => $case->id]);

    expect(ReplyToken::count())->toBe(2);

    $case->delete();

    expect(ReplyToken::count())->toBe(0);
});

it('enforces unique token at the database level', function () {
    ReplyToken::factory()->create(['token' => 'aaaaaaaaaaaaaaaaaaaa']);
    ReplyToken::factory()->create(['token' => 'aaaaaaaaaaaaaaaaaaaa']);
})->throws(QueryException::class);
