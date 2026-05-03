<?php

namespace App\Console\Commands;

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

/**
 * Daily sweep that handles tenant disengagement from
 * tenant_action_required cases. The dormancy clock starts at the most
 * recent transition INTO tenant_action_required for the case, which is
 * one of these events on case_events:
 *   - escalation_eligible (from awaiting_landlord or awaiting_tenant_review)
 *   - tenant_re_engaged   (from dormant)
 *   - hold_expired        (from on_hold)
 *
 * Behaviour by elapsed days since that entry:
 *   - >= 21 days  → transition to dormant (state machine writes
 *                   case_dormant as the canonical event)
 *   - >= 14 days  → write dormancy_reminder_sent with day_offset 14
 *                   (if not already sent since current entry)
 *   - >=  7 days  → write dormancy_reminder_sent with day_offset 7
 *                   (if not already sent since current entry)
 *
 * Mail dispatch for the reminder events themselves is wired in Phase 7
 * (DormancyReminder mailable). For Phase 5 the events serve as the
 * audit-trail / idempotency marker; Phase 7 will queue the actual mail
 * alongside the event write.
 *
 * dormancy_reminder_sent is a new entry in the case_events controlled
 * vocabulary (not in the design doc's initial list).
 */
class SweepDormancy extends Command
{
    private const ENTRY_EVENT_TYPES = [
        'escalation_eligible',
        'tenant_re_engaged',
        'hold_expired',
    ];

    private const REMINDER_EVENT_TYPE = 'dormancy_reminder_sent';

    private const DORMANCY_THRESHOLD_DAYS = 21;

    private const REMINDER_OFFSETS = [7, 14];

    protected $signature = 'cases:sweep-dormancy';

    protected $description = 'Send dormancy reminders and mark long-idle tenant_action_required cases dormant';

    public function handle(): int
    {
        $cases = RepairCase::query()
            ->where('status', CaseStatus::TenantActionRequired)
            ->get();

        $remindersSent = 0;
        $markedDormant = 0;

        foreach ($cases as $case) {
            $entryAt = $this->mostRecentEntryAt($case);
            if ($entryAt === null) {
                continue;
            }

            $daysSince = (int) $entryAt->diffInDays(now());

            if ($daysSince >= self::DORMANCY_THRESHOLD_DAYS) {
                $case->transitionTo(CaseStatus::Dormant);
                $markedDormant++;
                continue;
            }

            foreach (self::REMINDER_OFFSETS as $offset) {
                if ($daysSince >= $offset && ! $this->reminderAlreadySent($case, $offset, $entryAt)) {
                    $this->writeReminderEvent($case, $offset);
                    $remindersSent++;
                }
            }
        }

        $this->info("Reminders sent: {$remindersSent}; cases marked dormant: {$markedDormant}.");

        return self::SUCCESS;
    }

    private function mostRecentEntryAt(RepairCase $case): ?CarbonInterface
    {
        $event = $case->events()
            ->whereIn('event_type', self::ENTRY_EVENT_TYPES)
            ->orderByDesc('occurred_at')
            ->first();

        return $event?->occurred_at;
    }

    private function reminderAlreadySent(RepairCase $case, int $offset, CarbonInterface $entryAt): bool
    {
        return $case->events()
            ->where('event_type', self::REMINDER_EVENT_TYPE)
            ->where('occurred_at', '>=', $entryAt)
            ->where('meta->day_offset', $offset)
            ->exists();
    }

    private function writeReminderEvent(RepairCase $case, int $offset): void
    {
        $case->events()->create([
            'event_type' => self::REMINDER_EVENT_TYPE,
            'actor_label' => 'system',
            'occurred_at' => now(),
            'meta' => ['day_offset' => $offset],
        ]);
    }
}
