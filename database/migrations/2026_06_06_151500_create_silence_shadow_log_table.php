<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('silence_shadow_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')
                ->constrained('cases')
                ->cascadeOnDelete();
            $table->timestamp('swept_at');

            // Pure message-direction derivation (silence-phase-2a D0.1 +
            // ruling a). null when the case is in a no-clock status.
            $table->string('ball_with', 8)->nullable();
            $table->unsignedSmallInteger('silence_days')->nullable();

            // Fixed vocabulary: no_action, send_escalation, send_nudge,
            // transition_dormant_intent, transition_exhausted_intent.
            $table->string('intended_action', 40);

            $table->foreignId('intended_letter_template_id')
                ->nullable()
                ->constrained('letter_templates')
                ->restrictOnDelete();

            $table->unsignedTinyInteger('escalation_counter_value')->nullable();
            // Nudge number derived statelessly from silence_days vs
            // the snapshot thresholds (ruling c). Logged per row;
            // never stored on the case.
            $table->unsignedTinyInteger('nudge_number')->nullable();

            $table->string('reasoning', 255);

            // True when produced by `silence:sweep --pretend-today=YYYY-MM-DD`.
            // pretend rows are never confused with real-clock sweeps.
            $table->boolean('is_pretend')->default(false);
            $table->date('pretend_today')->nullable();

            $table->timestamps();

            $table->index(['case_id', 'swept_at']);
            $table->index(['is_pretend', 'swept_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('silence_shadow_log');
    }
};
