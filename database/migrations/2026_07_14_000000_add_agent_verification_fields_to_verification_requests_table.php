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
        Schema::table('verification_requests', function (Blueprint $table) {
            $table->string('business_license_path')->nullable();
            $table->string('national_id_path')->nullable();
            $table->string('selfie_path')->nullable();
            $table->text('business_address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verification_requests', function (Blueprint $table) {
            $table->dropColumn(['business_license_path', 'national_id_path', 'selfie_path', 'business_address']);
        });
    }
};
