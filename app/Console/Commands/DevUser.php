<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Creates one or more tenant users for local/staging development.
 *
 * Tenants only (is_admin = false); the admin is seeded by dev:reset. No
 * rental rows are created — the rentals feature is being retired.
 */
class DevUser extends Command
{
    private const EMAIL_DOMAIN = 'renters.rent';

    protected $signature = 'dev:user
        {--name=Tenant : Display name (index appended when auto-generating)}
        {--email= : Explicit email; requires --count=1}
        {--password=password : Password for the created tenant(s)}
        {--count=1 : Number of tenants to create}';

    protected $description = 'Create tenant user(s) for development (local/staging/preprod only)';

    public function handle(): int
    {
        if (! app()->environment('local', 'staging', 'preprod')) {
            $this->error('dev:user is restricted to the local, staging, and preprod environments.');

            return self::FAILURE;
        }

        $count = max(1, (int) $this->option('count'));
        $email = $this->option('email');

        if ($email !== null && $count > 1) {
            $this->error('--email cannot be combined with --count > 1 (one email cannot be reused).');

            return self::FAILURE;
        }

        for ($i = 0; $i < $count; $i++) {
            if ($email !== null) {
                $address = $email;
                $name = $this->option('name');
            } else {
                $n = $this->nextTenantIndex();
                $address = "tenant{$n}@".self::EMAIL_DOMAIN;
                $name = $this->option('name')." {$n}";
            }

            $user = User::create([
                'name' => $name,
                'email' => $address,
                'password' => Hash::make($this->option('password')),
                'is_admin' => false,
            ]);
            // email_verified_at is not mass-assignable; set it directly so the
            // tenant clears the `verified` middleware.
            $user->markEmailAsVerified();

            $this->line("tenant_created id={$user->id} email={$address}");
        }

        return self::SUCCESS;
    }

    /**
     * Lowest N (>= 1) for which tenant{N}@domain is not already taken, so
     * repeated runs keep producing fresh sequential addresses.
     */
    private function nextTenantIndex(): int
    {
        $n = 1;
        while (User::where('email', "tenant{$n}@".self::EMAIL_DOMAIN)->exists()) {
            $n++;
        }

        return $n;
    }
}
