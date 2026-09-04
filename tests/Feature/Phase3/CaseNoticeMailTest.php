<?php

use App\Mail\CaseNotice;
use App\Models\CaseMessage;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\ReplyToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Post-Phase-1 contract: CaseNotice is a "forwarder" Mailable. The
 * subject and body are FROZEN on the case_message row by SendCaseNotice
 * at orchestration time. The mailable reads them verbatim — it never
 * re-renders. The "given fixtures, produces stage-N letter content"
 * end-to-end belongs to SendCaseNotice and its freeze tests.
 *
 * Tests here cover only what the mailable itself owns:
 *   - envelope (From, Reply-To, subject mirror, tags)
 *   - content (body forwarded verbatim from message.body_raw)
 *   - constructor invariants (refuses to send without a frozen body/subject)
 */
function makeMailFixtures(array $messageOverrides = [], ?string $tokenValue = null): array
{
    $tenant = User::factory()->create(['name' => 'Alex Smith']);
    $property = Property::factory()->create([
        'address_line1' => '12 Example Street',
        'address_line2' => 'Flat 3',
        'city' => 'London',
        'postcode' => 'SW1A 1AA',
    ]);
    $category = RepairCategory::factory()->create(['label' => 'Damp and mould']);
    $case = RepairCase::factory()->withLandlord(['name' => 'Mr Landlord'])->create([
        'tenant_user_id' => $tenant->id,
        'property_id' => $property->id,
        'category_key' => $category->key,
    ]);
    $token = ReplyToken::factory()->create(array_filter([
        'case_id' => $case->id,
        'token' => $tokenValue,
    ]));
    $message = CaseMessage::factory()->create(array_merge([
        'case_id' => $case->id,
        'stage_at_send' => 1,
        'subject' => 'Repair issue notice 1 — 12 Example Street, SW1A 1AA (case test-slug)',
        'body_raw' => '<p>Frozen body — this is what arrives in the landlord\'s inbox verbatim.</p>',
        'tenant_statement' => 'Damp patch on the bedroom ceiling, getting worse since last month.',
    ], $messageOverrides));

    return compact('case', 'message', 'token');
}

it('content body is the message body_raw verbatim — no re-render', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeMailFixtures([
        'body_raw' => '<p>Exactly these bytes, please.</p>',
    ]);

    $rendered = (new CaseNotice($case, $message, $token))->render();

    expect($rendered)->toContain('Exactly these bytes, please.');
});

it('envelope subject mirrors the frozen message.subject', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeMailFixtures([
        'subject' => 'A bespoke frozen subject line',
    ]);

    expect((new CaseNotice($case, $message, $token))->envelope()->subject)
        ->toBe('A bespoke frozen subject line');
});

it('sets the From envelope to cases@mg.renters.rent with the tenant first name', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeMailFixtures();

    $envelope = (new CaseNotice($case, $message, $token))->envelope();
    $from = $envelope->from;

    expect($from->address)->toBe(config('services.mailgun.cases_from_address'));
    expect($from->name)->toBe('Alex via renters.rent');
});

it('sets the Reply-To envelope to the token-bound inbox address', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeMailFixtures(
        tokenValue: 'abcdefghij1234567890'
    );

    $envelope = (new CaseNotice($case, $message, $token))->envelope();

    expect($envelope->replyTo)->toHaveCount(1);
    expect($envelope->replyTo[0]->address)->toBe('abcdefghij1234567890@'.config('services.mailgun.inbound_domain'));
});

it('tags the message with the current environment for Mailgun log filtering', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeMailFixtures();

    $envelope = (new CaseNotice($case, $message, $token))->envelope();

    expect($envelope->tags)->toContain(app()->environment());
});

it('carries the case_message id as a Mailgun custom variable (#25 correlation key)', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeMailFixtures();

    $headers = (new CaseNotice($case, $message, $token))->headers();

    expect($headers->text)->toHaveKey('X-Mailgun-Variables');

    // Mailgun echoes custom variables back on every delivery event, so a
    // bounce can be matched to the letter that caused it. Recipient plus
    // timestamp cannot do that — the same landlord address legitimately
    // receives several letters across the ladder.
    expect(json_decode($headers->text['X-Mailgun-Variables'], true))
        ->toBe(['case_message_id' => $message->id]);
});

it('refuses to construct when message.body_raw is blank', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeMailFixtures([
        'body_raw' => '',
    ]);

    new CaseNotice($case, $message, $token);
})->throws(RuntimeException::class);

it('refuses to construct when message.subject is blank', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeMailFixtures([
        'subject' => null,
    ]);

    new CaseNotice($case, $message, $token);
})->throws(RuntimeException::class);
