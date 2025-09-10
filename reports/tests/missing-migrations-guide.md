# Missing Migrations Implementation Guide

## Critical Missing Database Tables

### 1. Catches Table Migration

**File**: `database/migrations/2024_12_17_000001_create_catches_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Required fields
            $table->string('species');
            $table->string('location');
            $table->datetime('caught_at');
            
            // Optional measurement fields
            $table->decimal('weight', 8, 3)->nullable();
            $table->decimal('length', 6, 2)->nullable();
            
            // Location fields
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('water_body')->nullable();
            
            // Fishing details
            $table->string('bait_lure')->nullable();
            $table->string('technique')->nullable();
            
            // Weather/environment
            $table->decimal('water_temp', 4, 2)->nullable();
            $table->decimal('air_temp', 4, 2)->nullable();
            $table->string('weather_conditions')->nullable();
            
            // Additional data
            $table->json('photos')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_released')->default(false);
            $table->boolean('is_personal_best')->default(false);
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Performance indexes
            $table->index('user_id');
            $table->index('species');
            $table->index('caught_at');
            $table->index('location');
            $table->index(['latitude', 'longitude']);
            $table->index('is_personal_best');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catches');
    }
};
```

### 2. Goals Table Migration

**File**: `database/migrations/2024_12_17_000002_create_goals_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Goal definition
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['species', 'weight', 'count', 'location', 'custom']);
            $table->json('criteria')->nullable(); // Goal-specific criteria
            
            // Progress tracking
            $table->integer('target_value')->nullable();
            $table->integer('current_value')->default(0);
            $table->json('progress_data')->nullable();
            
            // Timeline
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->date('completed_at')->nullable();
            
            // Status
            $table->enum('status', ['active', 'completed', 'paused', 'cancelled'])->default('active');
            $table->boolean('is_public')->default(false);
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Performance indexes
            $table->index('user_id');
            $table->index('type');
            $table->index('status');
            $table->index('target_date');
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
```

### 3. Weather Logs Table Migration

**File**: `database/migrations/2024_12_17_000003_create_weather_logs_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('catch_id')->nullable()->constrained()->onDelete('cascade');
            
            // Location
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('location_name')->nullable();
            
            // Weather data
            $table->decimal('temperature', 4, 2); // Celsius
            $table->decimal('feels_like', 4, 2)->nullable();
            $table->integer('humidity'); // Percentage
            $table->decimal('pressure', 6, 2)->nullable(); // hPa
            $table->decimal('wind_speed', 4, 2)->nullable(); // m/s
            $table->integer('wind_direction')->nullable(); // Degrees
            $table->string('weather_main'); // Clear, Rain, etc.
            $table->string('weather_description'); // Light rain, etc.
            $table->integer('visibility')->nullable(); // Meters
            $table->decimal('uv_index', 3, 1)->nullable();
            
            // Timestamps
            $table->datetime('logged_at');
            $table->timestamps();
            
            // Performance indexes
            $table->index('user_id');
            $table->index('catch_id');
            $table->index('logged_at');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_logs');
    }
};
```

## Commands to Create Migrations

```bash
# Create the migration files
php artisan make:migration create_catches_table
php artisan make:migration create_goals_table
php artisan make:migration create_weather_logs_table

# Run migrations
php artisan migrate

# Verify migrations
php artisan migrate:status
```

## Post-Migration Verification

### 1. Test Database Schema
```bash
# Check tables exist
php artisan tinker
>>> Schema::hasTable('catches')
>>> Schema::hasTable('goals')  
>>> Schema::hasTable('weather_logs')
```

### 2. Test Model Relationships
```php
// Test in tinker
$user = \App\Models\User::factory()->create();
$catch = $user->catches()->create([
    'species' => 'Test Bass',
    'location' => 'Test Lake',
    'caught_at' => now(),
]);
$goal = $user->goals()->create([
    'title' => 'Test Goal',
    'type' => 'count',
    'target_value' => 10,
]);
```

### 3. Run Full Test Suite
```bash
# Should now pass all tests
./vendor/bin/pest

# Check specific test coverage
./vendor/bin/pest --coverage
```

## Expected Test Results After Migration

With these migrations in place:

- ✅ **AC-1**: Authentication (already working)
- ✅ **AC-2**: Catch logging should work
- ✅ **AC-3**: Data retrieval and filtering should work
- ✅ **AC-4**: Location management should work  
- ✅ **AC-5**: Goal tracking should work
- ✅ **AC-6**: Validation should be testable
- ✅ **AC-7**: API responses (already working)
- ✅ **AC-8**: Performance should be measurable
- ✅ **AC-9**: Security (already working)
- ✅ **AC-10**: Database integrity should be testable

## Next Steps After Migration

1. **Test All Endpoints**
2. **Create Missing Test Files**
3. **Verify Controller Implementations**
4. **Run Integration Tests**
5. **Performance Benchmarking**

**Estimated Time**: 2-4 hours to implement and verify all migrations