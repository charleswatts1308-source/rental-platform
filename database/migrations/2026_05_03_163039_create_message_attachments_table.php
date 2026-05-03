<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_message_id')
                ->constrained('case_messages')
                ->cascadeOnDelete();
            $table->string('disk', 50)->default('private');
            $table->string('path', 500);
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->enum('direction', ['outbound', 'inbound']);
            $table->enum('scan_status', ['pending', 'clean', 'infected', 'skipped'])
                ->default('skipped');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
