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
        Schema::create('catches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Fish details
            $table->string('species');
            $table->decimal('weight', 8, 3)->nullable(); // in kg/lbs based on user preference
            $table->decimal('length', 8, 2)->nullable(); // in cm/inches based on user preference
            
            // Location data
            $table->string('location');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('water_body')->nullable(); // lake, river, ocean, etc.
            
            // Catch details
            $table->datetime('caught_at');
            $table->string('bait_lure')->nullable();
            $table->string('technique')->nullable();
            $table->decimal('water_temp', 5, 2)->nullable(); // in °C or °F
            $table->decimal('air_temp', 5, 2)->nullable(); // in °C or °F
            $table->string('weather_conditions')->nullable();
            
            // Photos and notes
            $table->json('photos')->nullable(); // array of photo URLs
            $table->text('notes')->nullable();
            
            // Metadata
            $table->boolean('is_released')->default(false);
            $table->boolean('is_personal_best')->default(false);
            $table->json('metadata')->nullable(); // additional structured data
            
            $table->timestamps();
            
            // Indexes for common queries
            $table->index(['user_id', 'caught_at']);
            $table->index(['user_id', 'species']);
            $table->index(['location']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catches');
    }
};