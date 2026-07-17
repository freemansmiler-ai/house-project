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
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // The advertiser
            $table->foreignId('property_id')->nullable()->constrained()->onDelete('set null'); // Optional promoted property listing
            $table->string('title');
            $table->string('banner_image_path');
            $table->string('target_url')->nullable();
            $table->string('placement'); // e.g. homepage_hero, sidebar_catalog
            $table->decimal('price_paid', 15, 2)->default(0.00);
            $table->enum('status', ['pending', 'active', 'paused', 'expired'])->default('pending');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('click_count')->default(0);
            $table->integer('impression_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
