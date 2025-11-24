<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id('rental_id');
            $table->string('user_id')->nullable(); // Matches ASP.NET Identity string ID
            $table->timestamp('date_created')->nullable();

            // Service Request Checkboxes
            $table->boolean('serv_req1_ll_status')->default(false);
            $table->boolean('serv_req2_ll_pu')->default(false);

            // RENTAL ADDRESS
            $table->string('rental_line1', 100)->nullable();
            $table->string('rental_line2', 100)->nullable();
            $table->string('rental_city', 100)->nullable();
            $table->string('rental_post_code', 100)->nullable();

            // AGENT INFORMATION
            $table->string('agent_contact_name', 100)->nullable();
            $table->string('agent_contact_email', 100)->nullable();
            $table->string('agent_company_name', 100)->nullable();
            $table->string('agent_company_email', 100)->nullable();
            $table->string('agent_line1', 100)->nullable();
            $table->string('agent_line2', 100)->nullable();
            $table->string('agent_city', 100)->nullable();
            $table->string('agent_post_code', 100)->nullable();

            // LANDLORD INFORMATION
            $table->string('landlord_contact_name', 100)->nullable();
            $table->string('landlord_contact_email', 100)->nullable();
            $table->string('landlord_company_name', 100)->nullable();
            $table->string('landlord_company_email', 100)->nullable();
            $table->string('landlord_line1', 100)->nullable();
            $table->string('landlord_line2', 100)->nullable();
            $table->string('landlord_city', 100)->nullable();
            $table->string('landlord_post_code', 100)->nullable();

            // LEASE DETAILS
            $table->string('lease_type', 100)->nullable();
            $table->date('lease_expiry_date')->nullable();
            $table->string('no_of_tenants', 100)->nullable();
            $table->string('rental_type', 100)->nullable();

            $table->timestamps(); // Laravel standard created_at/updated_at

            // Indexes for performance
            $table->unique('user_id'); // Matches your Index attribute
            $table->index('rental_post_code');
            $table->index('date_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
