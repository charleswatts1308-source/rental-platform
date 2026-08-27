<?php

namespace App\Console\Commands;

use App\Enums\CaseSeverity;
use App\Enums\CaseStatus;
use App\Enums\LandlordContactRole;
use App\Models\Property;
use App\Models\PropertyLandlordContact;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Creates repair case(s) in the initial Open state for local/staging dev,
 * including the supporting property and landlord contact rows.
 *
 * Mirrors the real CaseController::store scaffolding (tenant-owned property,
 * landlord resolved-or-created by email, case Open at stage 1 with the
 * case_opened event written by the model's booted hook) but deliberately
 * stops short of sending the first notice — dev:letter drives that, so a
 * case lands here ready for any --stage (decision 3a).
 */
class DevCase extends Command
{
    private const EMAIL_DOMAIN = 'renters.rent';

    protected $signature = 'dev:case
        {--tenant= : Tenant id or email (defaults to the first tenant)}
        {--category= : Repair category key (defaults to first active)}
        {--severity=routine : routine|serious|emergency}
        {--description= : Tenant description text (defaults to a generic line)}
        {--landlord-email= : Landlord email (defaults to sequential landlord{N})}
        {--landlord-role=landlord : landlord|agent}
        {--count=1 : Number of cases to create}';

    protected $description = 'Create repair case(s) in the Open state with property + landlord (local/staging/preprod only)';

    public function handle(): int
    {
        if (! app()->environment('local', 'staging', 'preprod')) {
            $this->error('dev:case is restricted to the local, staging, and preprod environments.');

            return self::FAILURE;
        }

        $tenant = $this->resolveTenant();
        if ($tenant === null) {
            return self::FAILURE;
        }

        $severity = CaseSeverity::tryFrom($this->option('severity'));
        if ($severity === null) {
            $this->error('Invalid --severity. Use one of: routine, serious, emergency.');

            return self::FAILURE;
        }

        $role = LandlordContactRole::tryFrom($this->option('landlord-role'));
        if ($role === null) {
            $this->error('Invalid --landlord-role. Use one of: landlord, agent.');

            return self::FAILURE;
        }

        $category = $this->resolveCategory();
        if ($category === null) {
            return self::FAILURE;
        }

        $count = max(1, (int) $this->option('count'));
        $landlordEmailOpt = $this->option('landlord-email');
        $description = $this->option('description')
            ?? 'A repair issue at this property — placeholder description for dev tooling.';

        for ($i = 0; $i < $count; $i++) {
            $property = Property::factory()->create([
                'registered_by_user_id' => $tenant->id,
            ]);

            $landlord = $this->resolveLandlordContact($property, $landlordEmailOpt, $role, $tenant->id);

            $case = RepairCase::factory()->create([
                'tenant_user_id' => $tenant->id,
                'property_id' => $property->id,
                'property_landlord_contact_id' => $landlord->id,
                'category_key' => $category->key,
                'severity' => $severity,
                'description' => $description,
                'status' => CaseStatus::Open,
                'current_stage' => 1,
            ]);

            $this->line(
                "case_created id={$case->id} url_slug={$case->url_slug} "
                ."tenant={$tenant->email} property_id={$property->id} "
                ."property_landlord_contact_id={$landlord->id} landlord={$landlord->email} "
                ."category={$category->key} severity={$severity->value} status={$case->status->value}"
            );
        }

        return self::SUCCESS;
    }

    private function resolveTenant(): ?User
    {
        $opt = $this->option('tenant');

        if ($opt === null) {
            $tenant = User::where('is_admin', false)->orderBy('id')->first();
            if ($tenant === null) {
                $this->error('No tenant users exist. Run `php artisan dev:user` first.');
            }

            return $tenant;
        }

        $tenant = is_numeric($opt)
            ? User::find((int) $opt)
            : User::where('email', $opt)->first();

        if ($tenant === null) {
            $this->error("No user found for --tenant={$opt}.");
        }

        return $tenant;
    }

    private function resolveCategory(): ?RepairCategory
    {
        $key = $this->option('category');

        if ($key === null) {
            $category = RepairCategory::where('active', true)->orderBy('sort_order')->first();
            if ($category === null) {
                $this->error('No active repair categories found (is the seeder run?).');
            }

            return $category;
        }

        $category = RepairCategory::where('key', $key)->where('active', true)->first();
        if ($category === null) {
            $this->error("No active repair category with key={$key}.");
        }

        return $category;
    }

    /**
     * Install the property's landlord contact, as the real create path
     * does. Without an explicit email, generates a fresh sequential
     * landlord{N}@domain.
     *
     * No find-or-create by email any more: the contact belongs to the
     * property, and two properties sharing an address is legitimate.
     */
    private function resolveLandlordContact(
        Property $property,
        ?string $emailOpt,
        LandlordContactRole $role,
        int $tenantId,
    ): PropertyLandlordContact {
        $email = $emailOpt !== null
            ? strtolower(trim($emailOpt))
            : $this->nextLandlordEmail();

        return $property->currentLandlordContact
            ?? $property->setLandlordContact([
                'email' => $email,
                'name' => 'Dev Landlord',
                'role' => $role,
            ], now(), $tenantId);
    }

    private function nextLandlordEmail(): string
    {
        $n = 1;
        while (PropertyLandlordContact::where('email', "landlord{$n}@".self::EMAIL_DOMAIN)->exists()) {
            $n++;
        }

        return "landlord{$n}@".self::EMAIL_DOMAIN;
    }
}
