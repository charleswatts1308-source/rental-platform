<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Models\CaseMessage;
use App\Models\LetterTemplate;
use App\Models\RepairCase;
use App\Services\Silence\SilenceClock;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Counter derivation per D3 + silence-phase-2a D0.3.
 *
 * Predicate: case_messages WHERE direction=outbound AND sender_role=
 * system AND stage_at_send IS NOT NULL. This works for fresh
 * Phase-1+ rows (both template_key and letter_template_id stamped),
 * for legacy rows (template_key set, letter_template_id null), and
 * naturally excludes future Phase-4 exhaustion_landlord rows
 * (stage_at_send=NULL) and Phase-3 tenant outbound rows
 * (sender_role=tenant).
 */
function makeCase(): RepairCase
{
    return RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);
}

function addEscalationLetter(RepairCase $case, int $stage, ?int $letterTemplateId): void
{
    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Outbound,
        'sender_role' => SenderRole::System,
        'stage_at_send' => $stage,
        'template_key' => "stage_{$stage}_test",
        'letter_template_id' => $letterTemplateId,
    ]);
}

it('counts zero on a fresh case with no messages', function () {
    $case = makeCase();
    expect((new SilenceClock)->escalationCounter($case))->toBe(0);
});

it('counts post-Phase-1 escalation rows via stage_at_send', function () {
    $template = LetterTemplate::where('code', 'landlord_wakeup_generic')->sole();
    $case = makeCase();

    addEscalationLetter($case, 1, $template->id);
    addEscalationLetter($case, 2, $template->id);
    addEscalationLetter($case, 3, $template->id);

    expect((new SilenceClock)->escalationCounter($case))->toBe(3);
});

it('counts legacy rows where letter_template_id is null but template_key + stage_at_send are set', function () {
    $case = makeCase();
    addEscalationLetter($case, 1, null);
    addEscalationLetter($case, 2, null);

    expect((new SilenceClock)->escalationCounter($case))->toBe(2);
});

it('counts mixed legacy + post-Phase-1 rows together', function () {
    $template = LetterTemplate::where('code', 'landlord_wakeup_generic')->sole();
    $case = makeCase();
    addEscalationLetter($case, 1, null);            // legacy
    addEscalationLetter($case, 2, $template->id);   // post-Phase-1

    expect((new SilenceClock)->escalationCounter($case))->toBe(2);
});

it('excludes inbound rows from the counter', function () {
    $case = makeCase();
    addEscalationLetter($case, 1, null);
    CaseMessage::factory()->inbound()->create(['case_id' => $case->id]);
    addEscalationLetter($case, 2, null);

    expect((new SilenceClock)->escalationCounter($case))->toBe(2);
});

it('excludes outbound rows whose stage_at_send is null — simulates future exhaustion_landlord', function () {
    $exhaustionTemplate = LetterTemplate::where('code', 'exhaustion_landlord_closer')->sole();
    $case = makeCase();

    addEscalationLetter($case, 1, null);
    // The Phase 4 exhaustion_landlord row pattern: outbound, system,
    // but no stage_at_send. Already-correct row shape thanks to the
    // existing seeder; this test pins that the predicate honours it.
    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Outbound,
        'sender_role' => SenderRole::System,
        'stage_at_send' => null,
        'template_key' => null,
        'letter_template_id' => $exhaustionTemplate->id,
    ]);

    expect((new SilenceClock)->escalationCounter($case))->toBe(1);
});

it('excludes outbound rows whose sender_role is tenant — simulates future Phase 3 tenant reply', function () {
    $case = makeCase();
    addEscalationLetter($case, 1, null);
    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Outbound,
        'sender_role' => SenderRole::Tenant,
        'stage_at_send' => null,
    ]);

    expect((new SilenceClock)->escalationCounter($case))->toBe(1);
});

it('counter is a ratchet — does not reset when a landlord reply arrives between letters (D3)', function () {
    $case = makeCase();
    addEscalationLetter($case, 1, null);
    CaseMessage::factory()->inbound()->create(['case_id' => $case->id]);
    addEscalationLetter($case, 2, null);
    CaseMessage::factory()->inbound()->create(['case_id' => $case->id]);
    addEscalationLetter($case, 3, null);

    expect((new SilenceClock)->escalationCounter($case))->toBe(3);
});
