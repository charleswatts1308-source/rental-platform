<?php

namespace App\Console\Commands;

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Builds a demo dataset of repair cases for local/staging dev.
 *
 * Default (spread) mode: resets to a clean slate, then creates one case in
 * each case status with distinct, plausible tenant/landlord/property data,
 * so the dashboard shows every state at once. Cases whose status implies an
 * in-flight reply keep a live reply token, so a follow-up dev:reply against
 * them behaves correctly.
 *
 * --outcome={status} switches to single-case mode: one case driven to that
 * end state. --no-reset layers onto existing state instead of resetting.
 *
 * Every step reuses a real building block — the dev:* commands and the
 * canonical RepairCase::transitionTo (the same method SweepEscalations,
 * SweepDormancy, and CaseController use) — rather than duplicating logic.
 */
class DevLifecycle extends Command
{
    private const ADMIN_EMAIL = 'admin@renters.rent';

    private const ADMIN_PASSWORD = 'password';

    /** Spread specs: [status, tenant name, tenant email, landlord email, landlord role, severity]. */
    private const SPECS = [
        ['open', 'Alice Adams', 'tenant1@example.test', 'maria.gomez@example.test', 'landlord', 'routine'],
        ['awaiting_landlord', 'Bob Brennan', 'tenant2@example.test', 'james.okoro@example.test', 'landlord', 'serious'],
        ['awaiting_tenant_review', 'Carla Diaz', 'tenant3@example.test', 'lettings@brightmove.example.test', 'agent', 'routine'],
        ['tenant_action_required', 'Derek Evans', 'tenant4@example.test', 'priya.shah@example.test', 'landlord', 'emergency'],
        ['on_hold', 'Fiona Grant', 'tenant5@example.test', 'tom.baker@example.test', 'landlord', 'routine'],
        ['resolved', 'George Hill', 'tenant6@example.test', 'lena.fischer@example.test', 'landlord', 'serious'],
        ['abandoned', 'Hannah Ito', 'tenant7@example.test', 'omar.haddad@example.test', 'landlord', 'routine'],
        ['dormant', 'Ian Jones', 'tenant8@example.test', 'sara.lindqvist@example.test', 'landlord', 'serious'],
    ];

    /** --outcome values mapped to the case status they drive to. */
    private const OUTCOMES = [
        'resolved' => 'resolved',
        'abandoned' => 'abandoned',
        'dormant' => 'dormant',
        'on_hold' => 'on_hold',
        'in_review' => 'awaiting_tenant_review',
    ];

    protected $signature = 'dev:lifecycle
        {--outcome= : Single-case mode: resolved|abandoned|dormant|on_hold|in_review}
        {--no-reset : Skip the initial dev:reset (layer onto existing state)}';

    protected $description = 'Build a demo spread of cases across every status, or a single case to a chosen outcome (local/staging only)';

    public function handle(): int
    {
        if (! app()->environment('local', 'staging')) {
            $this->error('dev:lifecycle is restricted to the local and staging environments.');

            return self::FAILURE;
        }

        $specs = $this->resolveSpecs();
        if ($specs === null) {
            return self::FAILURE;
        }

        [$adminEmail, $adminPassword] = $this->prepareState();

        $this->newLine();

        $categoryKeys = $this->ensureCategoryKeys();
        if ($categoryKeys === []) {
            return self::FAILURE;
        }

        $total = count($specs);
        $rows = [];

        foreach ($specs as $i => [$status, $name, $email, $landlordEmail, $role, $severity]) {
            $tenant = User::where('email', $email)->first();
            if ($tenant === null) {
                $this->callSilently('dev:user', ['--email' => $email, '--name' => $name]);
                $tenant = User::where('email', $email)->first();
            }

            $this->callSilently('dev:case', [
                '--tenant' => $email,
                '--landlord-email' => $landlordEmail,
                '--landlord-role' => $role,
                '--severity' => $severity,
                '--category' => $categoryKeys[$i % count($categoryKeys)],
            ]);
            $case = RepairCase::where('tenant_user_id', $tenant->id)->latest('id')->first();

            $action = $this->driveToStatus($case, $status, $tenant->id);
            $case->refresh();

            $this->line(sprintf(
                '[%d/%d] %-24s case_id=%-3d (%s)',
                $i + 1,
                $total,
                $case->status->value,
                $case->id,
                $action,
            ));

            $rows[] = [
                (string) $case->id,
                $case->status->value,
                $email,
                $landlordEmail,
                $this->tokenStatus($case),
            ];
        }

        $this->printSummary($rows, $adminEmail, $adminPassword);

        return self::SUCCESS;
    }

    /**
     * Active repair-category keys for the demo cases.
     *
     * repair_categories is reference data dev:reset preserves but does not
     * seed, so on a freshly-migrated DB (e.g. staging) it can be empty — which
     * previously caused a modulo-by-zero when cycling categories. Seed it
     * (idempotent RepairCategorySeeder) if empty, and fail clearly if seeding
     * still produces nothing.
     *
     * @return array<int, string>
     */
    private function ensureCategoryKeys(): array
    {
        $keys = RepairCategory::where('active', true)->orderBy('sort_order')->pluck('key')->all();

        if ($keys === []) {
            $this->callSilently('db:seed', ['--class' => 'RepairCategorySeeder', '--force' => true]);
            $keys = RepairCategory::where('active', true)->orderBy('sort_order')->pluck('key')->all();
        }

        if ($keys === []) {
            $this->error('No active repair categories found, and seeding produced none. Run `php artisan db:seed --class=RepairCategorySeeder` and retry.');
        }

        return $keys;
    }

    /**
     * @return array<int, array{0:string,1:string,2:string,3:string,4:string,5:string}>|null
     */
    private function resolveSpecs(): ?array
    {
        $outcome = $this->option('outcome');

        if ($outcome === null) {
            return self::SPECS;
        }

        if (! array_key_exists($outcome, self::OUTCOMES)) {
            $this->error('Invalid --outcome. Use one of: '.implode(', ', array_keys(self::OUTCOMES)).'.');

            return null;
        }

        return [[
            self::OUTCOMES[$outcome],
            'Sample Tenant',
            'tenant1@example.test',
            'maria.gomez@example.test',
            'landlord',
            'serious',
        ]];
    }

    /**
     * Reset (unless --no-reset) and report the admin credentials to use next.
     *
     * @return array{0:string,1:string} admin email and password
     */
    private function prepareState(): array
    {
        if ($this->option('no-reset')) {
            $admin = User::where('is_admin', true)->orderBy('id')->first();

            return [$admin?->email ?? '(no admin)', '(unchanged — reset skipped)'];
        }

        $this->call('dev:reset', [
            '--force' => true,
            '--email' => self::ADMIN_EMAIL,
            '--password' => self::ADMIN_PASSWORD,
        ]);

        return [self::ADMIN_EMAIL, self::ADMIN_PASSWORD];
    }

    /**
     * Drive a freshly-created Open case to the target status using the real
     * building blocks. Returns a short description of what was done.
     */
    private function driveToStatus(RepairCase $case, string $target, int $tenantId): string
    {
        $tenantContext = ['actor_user_id' => $tenantId, 'actor_label' => 'tenant'];

        return match ($target) {
            'open' => 'created',

            'awaiting_landlord' => $this->sendFirstLetter($case),

            'awaiting_tenant_review' => $this->sendFirstLetter($case)
                .', '.$this->landlordReplies($case),

            'tenant_action_required' => $this->sendFirstLetter($case)
                .', '.$this->escalate($case),

            'on_hold' => $this->sendFirstLetter($case)
                .', '.$this->landlordReplies($case)
                .', '.$this->transition($case, CaseStatus::OnHold, $tenantContext + ['hold_until' => now()->addDays(14)], 'placed on hold'),

            'resolved' => $this->sendFirstLetter($case)
                .', '.$this->landlordReplies($case)
                .', '.$this->transition($case, CaseStatus::Resolved, $tenantContext, 'resolved'),

            'abandoned' => $this->sendFirstLetter($case)
                .', '.$this->escalate($case)
                .', '.$this->transition($case, CaseStatus::Abandoned, $tenantContext, 'abandoned'),

            'dormant' => $this->sendFirstLetter($case)
                .', '.$this->escalate($case)
                .', '.$this->transition($case, CaseStatus::Dormant, [], 'gone dormant'),

            default => 'created',
        };
    }

    private function sendFirstLetter(RepairCase $case): string
    {
        $this->callSilently('dev:letter', ['--case' => $case->id, '--stage' => 1]);

        return 'letter stage 1 sent';
    }

    private function landlordReplies(RepairCase $case): string
    {
        $this->callSilently('dev:reply', ['--case' => $case->id]);

        return 'landlord replied';
    }

    private function escalate(RepairCase $case): string
    {
        $case->refresh()->transitionTo(CaseStatus::TenantActionRequired);

        return 'escalated';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function transition(RepairCase $case, CaseStatus $to, array $context, string $label): string
    {
        $case->refresh()->transitionTo($to, $context);

        return $label;
    }

    private function tokenStatus(RepairCase $case): string
    {
        $hasLiveToken = $case->replyTokens()
            ->whereNull('superseded_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        return ($hasLiveToken && $case->closed_at === null) ? 'live' : 'n/a';
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function printSummary(array $rows, string $adminEmail, string $adminPassword): void
    {
        $headers = ['case_id', 'status', 'tenant_email', 'landlord_email', 'token_status'];

        $widths = array_map('strlen', $headers);
        foreach ($rows as $row) {
            foreach ($row as $col => $value) {
                $widths[$col] = max($widths[$col], strlen($value));
            }
        }

        $render = fn (array $cells): string => '  '.implode('  ', array_map(
            fn ($cell, $col) => str_pad($cell, $widths[$col]),
            $cells,
            array_keys($cells),
        ));

        $this->newLine();
        $this->line("Lifecycle complete. Admin: {$adminEmail} / {$adminPassword}");
        $this->newLine();
        $this->line($render($headers));
        $this->line($render(array_map(fn ($w) => str_repeat('-', $w), $widths)));
        foreach ($rows as $row) {
            $this->line($render($row));
        }
    }
}
