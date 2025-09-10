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
        Schema::create('weather_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Location data
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('location_name');
            
            // Weather data from API
            $table->decimal('temperature', 5, 2); // °C or °F
            $table->decimal('feels_like', 5, 2)->nullable();
            $table->integer('humidity'); // percentage
            $table->decimal('pressure', 7, 2); // hPa or inHg
            $table->decimal('wind_speed', 5, 2)->nullable(); // m/s or mph
            $table->integer('wind_direction')->nullable(); // degrees
            $table->string('weather_main'); // Clear, Clouds, Rain, etc.
            $table->string('weather_description');
            $table->decimal('precipitation', 5, 2)->nullable(); // mm or inches
            $table->integer('cloud_cover')->nullable(); // percentage
            $table->decimal('uv_index', 3, 1)->nullable();
            $table->integer('visibility')->nullable(); // meters or feet
            
            // Timestamps
            $table->datetime('recorded_at');
            $table->string('api_source')->default('openweathermap');
            $table->json('raw_data')->nullable(); // store full API response for reference
            
            $table->timestamps();
            
            // Indexes for queries
            $table->index(['user_id', 'recorded_at']);
            $table->index(['latitude', 'longitude']);
            $table->index(['location_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_logs');
    }
};