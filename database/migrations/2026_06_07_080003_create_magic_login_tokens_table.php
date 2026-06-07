<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D12 — single-use signed-login tokens for tenant inbox links.
 *
 * Every Phase 3-touched outbound email to a tenant carries a magic
 * link to the case page; clicking signs the tenant in and lands them
 * on the case. The token is short-expiry (7 days) and single-use
 * (used_at is set on consumption).
 *
 * purpose is a categorical audit field (case_reply | dormancy_nudge |
 * auto_escalation | landlord_reply_received | hold_expired | ...).
 * Not a security boundary; just a debugging aid.
 *
 * case_id is nullable to allow future non-case-scoped uses; today
 * every row carries one and the consumer redirects accordingly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('token', 64)->unique();
            $table->string('purpose', 32);
            $table->foreignId('case_id')
                ->nullable()
                ->constrained('cases')
                ->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magic_login_tokens');
    }
};
