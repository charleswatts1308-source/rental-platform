<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SNAG #92 — page_views held every visitor's cookies, including signed-in
 * users' session and remember-me cookies (credentials), and full URLs
 * including query strings (password-reset tokens, reset email addresses,
 * signed verification parameters).
 *
 * Up: strip the query string from every stored page_name, then drop the
 * cookies column. Both are one-way by intent — the data removed is the
 * data that should never have been held.
 *
 * Down restores the column SHAPE only (nullable, so a rollback works with
 * rows present). It cannot restore the data, and must not try.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_views')
            ->where('page_name', 'like', '%?%')
            ->orderBy('page_view_id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('page_views')
                        ->where('page_view_id', $row->page_view_id)
                        ->update(['page_name' => strstr($row->page_name, '?', true)]);
                }
            }, 'page_view_id');

        Schema::table('page_views', function (Blueprint $table) {
            $table->dropColumn('cookies');
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->string('cookies', 2000)->nullable()->after('user_agent');
        });
    }
};
