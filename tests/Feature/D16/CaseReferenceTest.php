<?php

use App\Models\RepairCase;
use App\Support\CaseReference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a 6-char reference from the read-aloud-safe alphabet (#4)', function () {
    foreach (range(1, 300) as $ignored) {
        $ref = CaseReference::generate();

        expect(strlen($ref))->toBe(6);
        expect($ref)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/');
        // No ambiguous glyphs.
        expect($ref)->not->toMatch('/[IO01]/');
    }
});

it('uses the 32-symbol alphabet (24 letters + 8 digits, no I/O/0/1)', function () {
    expect(strlen(CaseReference::ALPHABET))->toBe(32);
    expect(CaseReference::ALPHABET)->not->toContain('I');
    expect(CaseReference::ALPHABET)->not->toContain('O');
    expect(CaseReference::ALPHABET)->not->toContain('0');
    expect(CaseReference::ALPHABET)->not->toContain('1');
});

it('mints case factory references in the new format', function () {
    $case = RepairCase::factory()->create();

    expect($case->url_slug)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/');
});
