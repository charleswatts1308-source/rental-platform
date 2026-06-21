<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * D16 / Surface A (A1) — full version history of every letter-template
     * edit. Append-only: one row per save, carrying the complete before/after
     * subject + body so the wording-of-record is reconstructable without a
     * code release. Separate from settings_change_hist by design (no
     * subject_type, not polymorphic — the two share a shape, not a storage).
     */
    public function up(): void
    {
        Schema::create('letter_text_change_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_template_id')->constrained();
            $table->unsignedInteger('version');
            $table->foreignId('edited_by_user_id')->constrained('users');
            $table->string('before_subject', 500)->nullable();
            $table->string('after_subject', 500)->nullable();
            $table->text('before_body')->nullable();
            $table->text('after_body')->nullable();
            // dateTime, not timestamp: avoids the implicit ON UPDATE
            // CURRENT_TIMESTAMP trap on a first-position timestamp column
            // (snag #18). Append-only, so no updated_at.
            $table->dateTime('created_at');

            // Cheap integrity: one version per template, no double-write.
            $table->unique(['letter_template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_text_change_history');
    }
};
