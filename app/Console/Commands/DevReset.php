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
        'cases',
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

    protected $description = 'Wipe all data to a clean slate and seed a single admin (local/staging only)';

    public function handle(): int
    {
        if (! app()->environment('local', 'staging')) {
            $this->error('dev:reset is restricted to the local and staging environments.');

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
            'is_admin' => true,
        ]);
        // email_verified_at is not mass-assignable; set it directly so the
        // admin clears the `verified` middleware on the admin routes.
        $admin->markEmailAsVerified();

        $this->line("admin_created={$email}");
        $this->line('admin_id=1');

        return self::SUCCESS;
    }
}
