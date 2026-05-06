<?php

namespace App\Console\Commands;

use App\Enums\CaseStatus;
use App\Mail\Notifications\EscalationEligible;
use App\Models\RepairCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Daily sweep that promotes awaiting_landlord cases past their
 * next_stage_eligible_at into tenant_action_required, where the tenant
 * picks the next move (send next letter, hold, resolve, abandon).
 *
 * Hold expiry is handled by cases:sweep-holds; this command targets only
 * awaiting_landlord so the two sweeps don't race for the same row.
 *
 * Idempotent: the status filter naturally excludes cases already
 * transitioned, so re-running within the same day is a no-op.
 */
class SweepEscalations extends Command
{
    protected $signature = 'cases:sweep-escalations';

    protected $description = 'Transition awaiting_landlord cases past their next_stage_eligible_at to tenant_action_required';

    public function handle(): int
    {
        $cases = RepairCase::query()
            ->where('status', CaseStatus::AwaitingLandlord)
            ->whereNotNull('next_stage_eligible_at')
            ->where('next_stage_eligible_at', '<=', now())
            ->whereNull('closed_at')
            ->get();

        $count = 0;
        foreach ($cases as $case) {
            $case->transitionTo(CaseStatus::TenantActionRequired);
            Mail::to($case->tenant->email)->queue(new EscalationEligible($case));
            $count++;
        }

        $this->info("Transitioned {$count} case(s) to tenant_action_required.");

        return self::SUCCESS;
    }
}
