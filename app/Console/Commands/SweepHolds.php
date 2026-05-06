<?php

namespace App\Console\Commands;

use App\Enums\CaseStatus;
use App\Mail\Notifications\HoldExpired;
use App\Models\RepairCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Daily sweep that releases on_hold cases whose hold_until has passed,
 * moving them to tenant_action_required so the tenant can choose the
 * next action. The state machine writes hold_expired as the canonical
 * event for this transition.
 *
 * hold_until is intentionally not cleared — it persists as a historical
 * record per the design. Idempotency comes from the status filter.
 */
class SweepHolds extends Command
{
    protected $signature = 'cases:sweep-holds';

    protected $description = 'Transition on_hold cases past their hold_until to tenant_action_required';

    public function handle(): int
    {
        $cases = RepairCase::query()
            ->where('status', CaseStatus::OnHold)
            ->whereNotNull('hold_until')
            ->where('hold_until', '<=', now())
            ->whereNull('closed_at')
            ->get();

        $count = 0;
        foreach ($cases as $case) {
            $case->transitionTo(CaseStatus::TenantActionRequired);
            Mail::to($case->tenant->email)->queue(new HoldExpired($case));
            $count++;
        }

        $this->info("Released {$count} hold(s) to tenant_action_required.");

        return self::SUCCESS;
    }
}
