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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // The creator/publisher of the property (Landlord or Agent)
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description');
            $table->decimal('price', 15, 2);
            $table->string('currency')->default('GHS'); // e.g., GHS, USD
            $table->string('period')->nullable(); // month, year, or null for sale
            $table->enum('category', ['residential', 'commercial', 'land', 'industrial'])->default('residential');
            $table->enum('type', ['apartment', 'house', 'townhouse', 'condo', 'office', 'retail', 'warehouse', 'land'])->default('house');
            $table->enum('status', ['pending', 'active', 'inactive', 'sold', 'rented'])->default('pending');
            $table->enum('deal_type', ['sale', 'rent'])->default('rent');
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->integer('area')->nullable(); // square meters or feet
            $table->string('location'); // street address
            $table->string('city'); // e.g. Accra, Kumasi, Tema
            $table->string('region'); // e.g. Greater Accra, Ashanti, Western
            $table->string('zip_code')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('video_url')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('view_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
