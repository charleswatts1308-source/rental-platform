<?php

use App\Actions\SendCaseNotice;
use App\Enums\CaseStatus;
use App\Mail\CaseNotice;
use App\Models\LandlordContact;
use App\Models\LetterTemplate;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Services\LetterTemplateRenderer;
use App\Services\ReplyTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Phase 1 silence-model — SendCaseNotice freezes the rendered subject
 * and body on the case_message row at orchestration time, alongside a
 * template id + updated_at snapshot. The mailable that follows must
 * never re-render — evidence is what was sent.
 *
 * Phase 3 — $tenantStatement parameter removed (D9: cases.description
 * is the source of truth for issue text). Escalation path is the
 * $isAutoEscalation branch (from AwaitingLandlord) — TAR-driven
 * escalation is demolished.
 */
function makeOpenCaseForFreeze(): RepairCase
{
    $contact = LandlordContact::factory()->create(['email' => 'landlord@example.com', 'name' => 'Mr Landlord']);
    $category = RepairCategory::factory()->create();

    return RepairCase::factory()->create([
        'landlord_contact_id' => $contact->id,
        'category_key' => $category->key,
        'description' => 'Damp patch on the bedroom ceiling, getting worse.',
        'status' => CaseStatus::Open,
        'current_stage' => 1,
    ]);
}

function sendAction(): SendCaseNotice
{
    return new SendCaseNotice(new ReplyTokenGenerator, new LetterTemplateRenderer);
}

it('stamps letter_template_id on the outbound case_message — points at the row used to render', function () {
    Mail::fake();
    $case = makeOpenCaseForFreeze();
    $generic = LetterTemplate::where('code', 'landlord_wakeup_generic')->sole();

    $message = sendAction()->execute($case);

    expect($message->letter_template_id)->toBe($generic->id);
});

it('stamps letter_template_updated_at as a snapshot of the template row at send time', function () {
    Mail::fake();
    $case = makeOpenCaseForFreeze();
    $generic = LetterTemplate::where('code', 'landlord_wakeup_generic')->sole();

    $message = sendAction()->execute($case);

    expect($message->letter_template_updated_at)->not->toBeNull();
    expect($message->letter_template_updated_at->equalTo($generic->updated_at))->toBeTrue();
});

it('body_raw is the rendered template body — substituted with case-specific values from cases.description', function () {
    Mail::fake();
    $case = makeOpenCaseForFreeze();

    $message = sendAction()->execute($case);

    expect($message->body_raw)->toContain('Damp patch on the bedroom ceiling, getting worse.');
    expect($message->body_raw)->toContain($case->url_slug);
    expect($message->body_raw)->toContain('Mr Landlord');
    expect($message->body_raw)->not->toContain('{{tenant_name}}');
    expect($message->body_raw)->not->toContain('{{landlord_name}}');
    expect($message->body_raw)->not->toContain('{{issue_description}}');
});

it('subject is the rendered template subject — substituted with notice_number and case_reference', function () {
    Mail::fake();
    $case = makeOpenCaseForFreeze();

    $message = sendAction()->execute($case);

    expect($message->subject)->toContain('notice 1');
    expect($message->subject)->toContain($case->url_slug);
    expect($message->subject)->not->toContain('{{notice_number}}');
    expect($message->subject)->not->toContain('{{case_reference}}');
});

it('throws a LogicException when no active escalation template is found', function () {
    Mail::fake();
    LetterTemplate::query()->update(['active' => false]);
    $case = makeOpenCaseForFreeze();

    sendAction()->execute($case);
})->throws(LogicException::class, 'No active escalation template');

it('freeze survives the queue: the mailable\'s envelope subject equals the frozen message.subject', function () {
    Mail::fake();
    $case = makeOpenCaseForFreeze();

    $message = sendAction()->execute($case);

    Mail::assertQueued(CaseNotice::class, function (CaseNotice $mail) use ($message) {
        return $mail->envelope()->subject === $message->fresh()->subject;
    });
});

it('escalation send (auto-escalation branch) uses the same generic row via the D1 fallback rule', function () {
    Mail::fake();
    $contact = LandlordContact::factory()->create(['email' => 'landlord@example.com']);
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->create([
        'landlord_contact_id' => $contact->id,
        'category_key' => $category->key,
        'description' => 'Damp patch.',
        'status' => CaseStatus::AwaitingLandlord,
        'current_stage' => 2,
        'silence_clock_started_at' => now()->subDays(20),
        'silence_settings_snapshot' => \App\Services\Silence\SilenceClock::snapshotCurrentSettings(),
    ]);
    \App\Models\ReplyToken::factory()->create([
        'case_id' => $case->id,
        'bound_email' => $contact->email,
        'superseded_at' => null,
    ]);
    $generic = LetterTemplate::where('code', 'landlord_wakeup_generic')->sole();

    $message = sendAction()->execute($case);

    expect($message->stage_at_send)->toBe(3);
    expect($message->letter_template_id)->toBe($generic->id);
    expect($message->subject)->toContain('notice 3');
});
