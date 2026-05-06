<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')
                ->constrained('cases')
                ->cascadeOnDelete();
            $table->enum('direction', ['outbound', 'inbound']);
            $table->enum('sender_role', ['system', 'tenant', 'landlord']);
            $table->unsignedTinyInteger('stage_at_send')->nullable();
            $table->string('template_key', 64)->nullable();
            $table->string('subject', 500)->nullable();
            $table->text('body_raw');
            $table->text('body_sanitised')->nullable();
            $table->text('tenant_statement')->nullable();
            $table->string('from_address_raw')->nullable();
            $table->string('to_address_raw')->nullable();
            $table->boolean('spf_pass')->nullable();
            $table->boolean('dkim_pass')->nullable();
            $table->string('mailgun_message_id')->nullable();
            $table->string('quarantine_reason', 100)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['case_id', 'sent_at']);
            $table->index(['case_id', 'received_at']);
            $table->index('mailgun_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_messages');
    }
};
