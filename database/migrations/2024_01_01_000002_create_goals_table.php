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
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['species', 'weight', 'count', 'location', 'custom']);
            
            // Goal criteria (JSON for flexibility)
            $table->json('criteria'); // e.g., {"species": "bass", "min_weight": 5, "timeframe": "yearly"}
            
            // Progress tracking
            $table->integer('target_value')->nullable(); // for numeric goals
            $table->integer('current_value')->default(0); // for numeric goals
            $table->json('progress_data')->nullable(); // detailed progress tracking
            
            // Timeline
            $table->date('start_date');
            $table->date('target_date');
            $table->date('completed_at')->nullable();
            
            // Status and metadata
            $table->enum('status', ['active', 'completed', 'paused', 'cancelled'])->default('active');
            $table->boolean('is_public')->default(false); // for sharing goals
            $table->json('metadata')->nullable(); // additional goal-specific data
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'type']);
            $table->index(['target_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};