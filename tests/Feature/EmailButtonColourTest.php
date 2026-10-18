<?php

use App\Models\LetterTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Button policy (docs/button-policy.md) — email buttons are the logo
 * green #047857, never the old Tailwind blue #2563eb.
 */

test('no seeded email template carries the old blue button colour', function () {
    expect(LetterTemplate::where('body', 'like', '%#2563eb%')->count())->toBe(0);
    expect(LetterTemplate::where('body', 'like', '%background: #047857%')->count())->toBeGreaterThan(0);
});

test('the migration recolours an old-blue row on a live box and keeps its edited wording', function () {
    $row = LetterTemplate::where('code', 'welcome_registered')->firstOrFail();
    $row->update(['body' => '<p>Edited on the box.</p><p><a href="{{magic_link}}" style="padding: 10px 16px; background: #2563eb; color: #fff;">Open this case</a></p>']);

    $migration = require database_path('migrations/2026_10_18_130000_email_buttons_logo_green.php');
    $migration->up();

    $body = $row->fresh()->body;
    expect($body)->toContain('background: #047857')
        ->and($body)->not->toContain('#2563eb')
        ->and($body)->toContain('<p>Edited on the box.</p>');
});
