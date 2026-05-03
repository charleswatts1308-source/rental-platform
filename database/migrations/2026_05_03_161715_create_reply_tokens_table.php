<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reply_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')
                ->constrained('cases')
                ->cascadeOnDelete();
            $table->char('token', 20)->unique();
            $table->string('bound_email');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['case_id', 'superseded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reply_tokens');
    }
};
