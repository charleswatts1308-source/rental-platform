<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only migration — inserts the `attachments.first_notice_max` setting
 * row on environments that already exist.
 *
 * No table is created or altered, so the CLAUDE.md #18 MariaDB
 * SHOW CREATE TABLE gate does not apply here.
 *
 * Why a migration rather than just re-running SettingSeeder: the seeder
 * uses updateOrCreate, so running it on a long-lived environment would
 * reset every OTHER key back to its shipped default, discarding any
 * tuning done through the admin surface. This touches one key and only
 * when it is absent.
 */
return new class extends Migration
{
    private const KEY = 'attachments.first_notice_max';

    public function up(): void
    {
        $exists = DB::table('settings')->where('key', self::KEY)->exists();

        if ($exists) {
            return;
        }

        DB::table('settings')->insert([
            'key' => self::KEY,
            'value' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
    }
};
