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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Phase 1 silence-model: SendCaseNotice freezes the rendered subject
 * and body on the case_message row at orchestration time, alongside a
 * template id + updated_at snapshot. The mailable that follows must
 * never re-render — evidence is what was sent.
 *
 * Pest's beforeEach (tests/Pest.php) seeds LetterTemplateSeeder so the
 * generic landlord wake-up row exists for these tests.
 */
function makeOpenCaseForFreeze(): RepairCase
{
    $contact = LandlordContact::factory()->create(['email' => 'landlord@example.com', 'name' => 'Mr Landlord']);
    $category = RepairCategory::factory()->create();

    return RepairCase::factory()->create([
        'landlord_contact_id' => $contact->id,
        'category_key' => $category->key,
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

    $message = sendAction()->execute($case, 'Damp in the bedroom.');

    expect($message->letter_template_id)->toBe($generic->id);
});

it('stamps letter_template_updated_at as a snapshot of the template row at send time', function () {
    Mail::fake();
    $case = makeOpenCaseForFreeze();
    $generic = LetterTemplate::where('code', 'landlord_wakeup_generic')->sole();

    $message = sendAction()->execute($case, 'Damp in the bedroom.');

    expect($message->letter_template_updated_at)->not->toBeNull();
    expect($message->letter_template_updated_at->equalTo($generic->updated_at))->toBeTrue();
});

it('body_raw is the rendered template body — substituted with case-specific values', function () {
    Mail::fake();
    $case = makeOpenCaseForFreeze();

    $message = sendAction()->execute($case, 'Damp patch on the bedroom ceiling, getting worse.');

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

    $message = sendAction()->execute($case, 'Damp.');

    expect($message->subject)->toContain('notice 1');
    expect($message->subject)->toContain($case->url_slug);
    expect($message->subject)->not->toContain('{{notice_number}}');
    expect($message->subject)->not->toContain('{{case_reference}}');
});

// "dual-writes template_key and letter_template_id" — DELETED per
// silence-phase-2b D0.1 #16. The dual-write closed the Phase 1
// transitional window; template_key is dropped in 2b. The
// letter_template_id assertion is now covered by the existing
// "stamps letter_template_id" test in this same file.

it('throws a LogicException when no active escalation template is found', function () {
    Mail::fake();
    LetterTemplate::query()->update(['active' => false]);
    $case = makeOpenCaseForFreeze();

    sendAction()->execute($case);
})->throws(LogicException::class, 'No active escalation template');

it('freeze survives the queue: the mailable\'s envelope subject equals the frozen message.subject', function () {
    Mail::fake();
    $case = makeOpenCaseForFreeze();

    $message = sendAction()->execute($case, 'Damp.');

    Mail::assertQueued(CaseNotice::class, function (CaseNotice $mail) use ($message) {
        return $mail->envelope()->subject === $message->fresh()->subject;
    });
});

it('escalation send uses the same generic row via the D1 fallback rule when no per-stage row exists', function () {
    Mail::fake();
    $contact = LandlordContact::factory()->create(['email' => 'landlord@example.com']);
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->create([
        'landlord_contact_id' => $contact->id,
        'category_key' => $category->key,
        'status' => CaseStatus::TenantActionRequired,
        'current_stage' => 2,
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
