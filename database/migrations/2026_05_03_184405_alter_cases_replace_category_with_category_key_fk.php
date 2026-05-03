<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->string('category_key', 50)->after('landlord_contact_id');
            $table->foreign('category_key')
                ->references('key')->on('repair_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['category_key']);
            $table->dropColumn('category_key');
        });

        Schema::table('cases', function (Blueprint $table) {
            $table->string('category', 50)->after('landlord_contact_id');
        });
    }
};
