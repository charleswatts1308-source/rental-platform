<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes all application data to a clean slate and seeds a single admin as
 * the first users row, for local/staging development only.
 *
 * Tables are TRUNCATEd (not DELETEd) so AUTO_INCREMENT resets and the new
 * admin lands at id 1. FK checks are disabled around the truncate because
 * several of these tables are referenced by restrict/cascade FKs.
 *
 * Preserved: repair_categories (seeded reference data) and all framework
 * tables (migrations, cache, jobs, sessions, etc.).
 *
 * Note: repair_categories content is deliberately NOT truncated OR seeded
 * here — reset only clears transactional data and leaves reference data as it
 * found it. Seeding repair_categories (when empty, e.g. a freshly-migrated
 * staging DB) is dev:lifecycle's responsibility, not dev:reset's.
 */
class DevReset extends Command
{
    /**
     * Data tables wiped on reset. Order is irrelevant since FK checks are
     * disabled, but they are grouped here for readability.
     */
    private const TABLES = [
        // LLCS domain
        'message_attachments',
        'case_events',
        'reply_tokens',
        'case_messages',
        'silence_shadow_log',
        'magic_login_tokens',
        'cases',
        'property_landlord_contacts',
        'properties',
        'landlord_contacts',
        // standalone attachment table (rentals removed; not yet re-wired)
        'file_attachments',
        // users + dependents
        'contact_messages',
        'users',
        // standalone data
        'page_views',
        'error_logs',
        'uploaded_files',
    ];

    protected $signature = 'dev:reset
        {--email=admin@renters.rent : Email for the seeded admin (row 1)}
        {--password=password : Password for the seeded admin}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe all data to a clean slate and seed a single admin (local/staging/preprod only)';

    public function handle(): int
    {
        if (! app()->environment('local', 'staging', 'preprod')) {
            $this->error('dev:reset is restricted to the local, staging, and preprod environments.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This wipes ALL data (users, cases, rentals, ...) and reseeds one admin. Continue?')) {
            $this->line('aborted=1');

            return self::FAILURE;
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach (self::TABLES as $table) {
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $this->line("{$table}_truncated={$count}");
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $email = $this->option('email');

        $admin = User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => Hash::make($this->option('password')),
        ]);
        // is_admin is not mass-assignable (privilege boundary); set it
        // directly. Same for email_verified_at, so the admin clears the
        // `verified` middleware on the admin routes.
        $admin->forceFill(['is_admin' => true])->save();
        $admin->markEmailAsVerified();

        $this->line("admin_created={$email}");
        $this->line('admin_id=1');

        return self::SUCCESS;
    }
}
