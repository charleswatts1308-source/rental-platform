<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_messages', function (Blueprint $table) {
            // Nullable: inbound messages and future free-text tenant replies have none.
            // Restrict-on-delete so a template row in use can't be removed silently.
            $table->foreignId('letter_template_id')
                ->nullable()
                ->after('template_key')
                ->constrained('letter_templates')
                ->restrictOnDelete();

            // Snapshot of letter_templates.updated_at at send time —
            // answers "which wording was in force".
            $table->timestamp('letter_template_updated_at')
                ->nullable()
                ->after('letter_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('case_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('letter_template_id');
            $table->dropColumn('letter_template_updated_at');
        });
    }
};
