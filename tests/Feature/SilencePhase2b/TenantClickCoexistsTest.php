<?php

use App\Actions\SendCaseNotice;
use App\Enums\CaseStatus;
use App\Mail\CaseNotice;
use App\Mail\Notifications\AutoEscalationTenantNotice;
use App\Models\LandlordContact;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\ReplyToken;
use App\Models\User;
use App\Services\LetterTemplateRenderer;
use App\Services\ReplyTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * D7 interim: the tenant "send next notice" click path
 * (CaseController::sendNext) survives 2b demolition. It coexists
 * with silence:sweep auto-escalation — they're different entry
 * states (TenantActionRequired vs AwaitingLandlord) producing
 * different audit-trail events (notice_sent + stage_advanced vs
 * auto_escalation_sent).
 *
 * The tenant click is the future home of "tenant-initiated
 * escalation on unsatisfactory reply" (the D7 path). Until Phase 3
 * resolves D7, the click stays.
 */
function tarCaseForClick(): RepairCase
{
    $contact = LandlordContact::factory()->create(['email' => 'landlord@example.com', 'name' => 'Mr Landlord']);
    $category = RepairCategory::factory()->create();
    $tenant = User::factory()->create();

    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'landlord_contact_id' => $contact->id,
        'category_key' => $category->key,
        'status' => CaseStatus::TenantActionRequired,
        'current_stage' => 1,
    ]);

    ReplyToken::factory()->create([
        'case_id' => $case->id,
        'bound_email' => $contact->email,
        'superseded_at' => null,
    ]);

    return $case;
}

it('tenant click still works post-2b — SendCaseNotice accepts TenantActionRequired and transitions to AwaitingLandlord', function () {
    Mail::fake();
    $case = tarCaseForClick();
    $action = new SendCaseNotice(new ReplyTokenGenerator, new LetterTemplateRenderer);

    $message = $action->execute($case);

    expect($message->stage_at_send)->toBe(2);
    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
    expect($case->fresh()->current_stage)->toBe(2);
    Mail::assertQueued(CaseNotice::class);
});

it('tenant click writes notice_sent + stage_advanced (not auto_escalation_sent)', function () {
    Mail::fake();
    $case = tarCaseForClick();
    $action = new SendCaseNotice(new ReplyTokenGenerator, new LetterTemplateRenderer);

    $action->execute($case);

    $events = $case->fresh()->events()->pluck('event_type')->all();
    expect($events)->toContain('notice_sent');
    expect($events)->toContain('stage_advanced');
    expect($events)->toContain('escalation_confirmed_by_tenant');
    expect($events)->not->toContain('auto_escalation_sent');
});

it('tenant click does NOT fire the AutoEscalationTenantNotice — that mailable is sweep-only', function () {
    Mail::fake();
    $case = tarCaseForClick();
    $action = new SendCaseNotice(new ReplyTokenGenerator, new LetterTemplateRenderer);

    $action->execute($case);

    Mail::assertNotQueued(AutoEscalationTenantNotice::class);
});

it('click then sweep same day: no double-send (sweep finds the clock fresh and skips)', function () {
    Mail::fake();
    $case = tarCaseForClick();
    $action = new SendCaseNotice(new ReplyTokenGenerator, new LetterTemplateRenderer);

    // Tenant click first — sends, restarts the clock.
    $action->execute($case);

    // Sweep runs immediately afterwards — clock is 0 days old, well
    // below the 14-day interval; verdict should be no_action.
    $this->artisan('silence:sweep')->assertSuccessful();

    // Only the click's send is queued; no auto-escalation followed.
    expect(Mail::queued(CaseNotice::class)->count())->toBe(1);
    Mail::assertNotQueued(AutoEscalationTenantNotice::class);
});
