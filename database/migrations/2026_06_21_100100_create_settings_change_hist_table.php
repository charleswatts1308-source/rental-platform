<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * D16 / Surface B (B3) — audit log of every settings value change.
     * Flat scalars, old + new value on the SAME row (one row is one
     * complete, self-contained change — readable without the prior row).
     * Deliberately separate from letter_text_change_history: no version
     * concept, no subject_type discriminator, not polymorphic.
     * Append-only.
     */
    public function up(): void
    {
        Schema::create('settings_change_hist', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key');
            $table->foreignId('edited_by_user_id')->constrained('users');
            $table->string('old_value')->nullable();
            $table->string('new_value');
            // dateTime, not timestamp: avoids the implicit ON UPDATE
            // CURRENT_TIMESTAMP trap (snag #18). Append-only, no updated_at.
            $table->dateTime('created_at');

            $table->index('setting_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_change_hist');
    }
};
