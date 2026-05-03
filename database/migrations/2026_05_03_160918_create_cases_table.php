<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->char('url_slug', 12)->unique();
            $table->foreignId('tenant_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('property_id')
                ->constrained('properties')
                ->restrictOnDelete();
            $table->foreignId('landlord_contact_id')
                ->constrained('landlord_contacts')
                ->restrictOnDelete();
            $table->string('category', 50);
            $table->enum('severity', ['routine', 'serious', 'emergency'])->default('routine');
            $table->enum('status', [
                'open',
                'awaiting_landlord',
                'awaiting_tenant_review',
                'tenant_action_required',
                'on_hold',
                'resolved',
                'abandoned',
                'dormant',
            ])->default('open');
            $table->unsignedTinyInteger('current_stage')->default(1);
            $table->timestamp('next_stage_eligible_at')->nullable();
            $table->timestamp('hold_until')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_user_id', 'status']);
            $table->index(['status', 'next_stage_eligible_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
