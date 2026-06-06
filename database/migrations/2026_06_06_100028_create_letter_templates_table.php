<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('description');
            $table->string('subject', 500);
            $table->text('body');
            // Open string set, per repair_categories precedent. Known values today:
            // escalation, tenant_nudge, exhaustion_landlord, tenant_notification.
            $table->string('type', 32);
            // NULL = generic fallback. Non-NULL only meaningful for type=escalation,
            // where the renderer picks stage=N if present, else falls back to NULL.
            $table->unsignedTinyInteger('stage')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['type', 'stage', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_templates');
    }
};
