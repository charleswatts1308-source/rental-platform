<?php

use App\Actions\HandleInboundReply;
use App\Actions\SendCaseNotice;
use App\Enums\CaseStatus;
use App\Enums\LandlordContactRole;
use App\Mail\CaseNotice;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\ReplyToken;
use App\Services\LetterTemplateRenderer;
use App\Services\MagicLinkGenerator;
use App\Services\ReplyTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function routingSendAction(): SendCaseNotice
{
    return new SendCaseNotice(new ReplyTokenGenerator, new LetterTemplateRenderer);
}

function caseAtAddress(string $email, ?string $name = null): RepairCase
{
    $category = RepairCategory::factory()->create();

    $case = RepairCase::factory()->create([
        'category_key' => $category->key,
        'status' => CaseStatus::Open,
        'current_stage' => 1,
    ]);

    $case->property->setLandlordContact(
        ['email' => $email, 'name' => $name, 'role' => LandlordContactRole::Landlord],
        now(),
        $case->tenant_user_id,
    );

    return $case->fresh();
}

function correctAddressTo(RepairCase $case, string $email, ?string $name = null): void
{
    $case->property->setLandlordContact(
        ['email' => $email, 'name' => $name, 'role' => LandlordContactRole::Landlord],
        now(),
        $case->tenant_user_id,
    );

    $case->unsetRelation('property');
}

/*
 * The core claim of the move: routing resolves the PROPERTY's current
 * contact, not the contact the case was raised with. If these fail, snag
 * #24 is not closed no matter what the property page lets you type.
 */

it('sends the first letter to the property current contact', function () {
    Mail::fake();
    $case = caseAtAddress('landlord@example.com', 'Larry Landlord');

    routingSendAction()->execute($case);

    expect($case->messages()->latest('id')->first()->to_address_raw)
        ->toBe('landlord@example.com');
});

it('sends the NEXT letter to a corrected address', function () {
    Mail::fake();
    $case = caseAtAddress('typo@example.com', 'Larry Landlord');
    routingSendAction()->execute($case);

    correctAddressTo($case, 'correct@example.com', 'Larry Landlord');
    routingSendAction()->execute($case->fresh());

    expect($case->messages()->latest('id')->first()->to_address_raw)
        ->toBe('correct@example.com');
});

it('queues the actual envelope to the corrected address, not just the frozen row', function () {
    Mail::fake();
    $case = caseAtAddress('typo@example.com');
    routingSendAction()->execute($case);
    correctAddressTo($case, 'correct@example.com');

    routingSendAction()->execute($case->fresh());

    Mail::assertQueued(CaseNotice::class, fn ($mail) => $mail->hasTo('correct@example.com'));
});

it('binds the new reply token to the corrected address', function () {
    Mail::fake();
    $case = caseAtAddress('typo@example.com');
    routingSendAction()->execute($case);
    correctAddressTo($case, 'correct@example.com');

    routingSendAction()->execute($case->fresh());

    expect(ReplyToken::where('case_id', $case->id)->whereNull('superseded_at')->sole()->bound_email)
        ->toBe('correct@example.com');
});

it('carries a corrected landlord NAME into the next letter salutation', function () {
    Mail::fake();
    $case = caseAtAddress('a@example.com', 'Wrong Name');
    routingSendAction()->execute($case);

    correctAddressTo($case, 'a@example.com', 'Right Name');
    routingSendAction()->execute($case->fresh());

    expect($case->messages()->latest('id')->first()->body_raw)
        ->toContain('Right Name');
});

/*
 * The evidential constraint. A correction must not reach back into what
 * was already sent.
 */
it('leaves every previously frozen case_messages row byte-identical after a correction', function () {
    Mail::fake();
    $case = caseAtAddress('typo@example.com', 'Larry Landlord');
    routingSendAction()->execute($case);

    $before = DB::table('case_messages')->orderBy('id')->get()->toArray();

    correctAddressTo($case, 'correct@example.com', 'Larry Landlord');

    expect(DB::table('case_messages')->orderBy('id')->get()->toArray())->toEqual($before);
});

it('keeps the first letter addressed to the typo it was actually sent to', function () {
    Mail::fake();
    $case = caseAtAddress('typo@example.com');
    routingSendAction()->execute($case);
    $first = $case->messages()->latest('id')->first();

    correctAddressTo($case, 'correct@example.com');
    routingSendAction()->execute($case->fresh());

    expect($first->fresh()->to_address_raw)->toBe('typo@example.com')
        ->and($case->messages()->count())->toBe(2);
});

it('does not change the case provenance FK when the property contact is corrected', function () {
    Mail::fake();
    $case = caseAtAddress('typo@example.com');
    $openedWith = $case->property_landlord_contact_id;

    correctAddressTo($case, 'correct@example.com');

    expect($case->fresh()->property_landlord_contact_id)->toBe($openedWith);
});

/*
 * The known behaviour change, asserted rather than left to be discovered.
 */
it('quarantines a reply from the SUPERSEDED address after a correction', function () {
    Mail::fake();
    $case = caseAtAddress('typo@example.com');
    routingSendAction()->execute($case);
    correctAddressTo($case, 'correct@example.com');
    routingSendAction()->execute($case->fresh());

    $token = ReplyToken::where('case_id', $case->id)->whereNull('superseded_at')->sole();

    $action = new HandleInboundReply(new LetterTemplateRenderer, new MagicLinkGenerator);
    $action->execute([
        'recipient' => $token->token.'@'.config('services.mailgun.inbound_domain'),
        'from' => 'typo@example.com',
        'subject' => 'Re: repair',
        'body-plain' => 'Sent from the old address.',
    ]);

    expect($case->messages()->latest('id')->first()->quarantine_reason)->not->toBeNull();
});

it('accepts a reply from the corrected address', function () {
    Mail::fake();
    $case = caseAtAddress('typo@example.com');
    routingSendAction()->execute($case);
    correctAddressTo($case, 'correct@example.com');
    routingSendAction()->execute($case->fresh());

    $token = ReplyToken::where('case_id', $case->id)->whereNull('superseded_at')->sole();

    $action = new HandleInboundReply(new LetterTemplateRenderer, new MagicLinkGenerator);
    $action->execute([
        'recipient' => $token->token.'@'.config('services.mailgun.inbound_domain'),
        'from' => 'correct@example.com',
        'subject' => 'Re: repair',
        'body-plain' => 'Sent from the corrected address.',
    ]);

    expect($case->messages()->latest('id')->first()->quarantine_reason)->toBeNull();
});

/*
 * A correction is an edit, not an escalation. This is the D3 ratchet
 * guard: if correcting a typo ever advances the counter, the tenant pays
 * a stage for a mistake.
 */
it('does not advance the escalation counter when a contact is corrected', function () {
    Mail::fake();
    $case = caseAtAddress('typo@example.com');
    routingSendAction()->execute($case);

    $stageBefore = $case->fresh()->current_stage;
    $escalationRowsBefore = $case->messages()->whereNotNull('stage_at_send')->count();

    correctAddressTo($case, 'correct@example.com');

    expect($case->fresh()->current_stage)->toBe($stageBefore)
        ->and($case->messages()->whereNotNull('stage_at_send')->count())->toBe($escalationRowsBefore);
});

it('does not send anything at all when a contact is corrected', function () {
    Mail::fake();
    $case = caseAtAddress('typo@example.com');
    routingSendAction()->execute($case);
    Mail::fake();

    correctAddressTo($case, 'correct@example.com');

    Mail::assertNothingQueued();
});

it('refuses to send when the property has no current contact', function () {
    $case = caseAtAddress('landlord@example.com');
    $case->property->landlordContacts()->update(['superseded_at' => now(), 'is_current' => null]);
    $case->unsetRelation('property');

    routingSendAction()->execute($case);
})->throws(RuntimeException::class);

it('resolves the recipient once, so token binding and frozen row cannot disagree', function () {
    Mail::fake();
    $case = caseAtAddress('landlord@example.com');

    routingSendAction()->execute($case);

    $message = $case->messages()->latest('id')->first();
    $token = ReplyToken::where('case_id', $case->id)->whereNull('superseded_at')->sole();

    expect($message->to_address_raw)->toBe($token->bound_email);
});
