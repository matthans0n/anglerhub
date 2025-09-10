---
title: "Feature Flags Strategy for AnglerHub"
phase: "Ship"
deployment_steps: 
  - "Implement feature flag service"
  - "Configure environment-based flags"
  - "Document flag usage patterns"
  - "Set up flag monitoring"
monitoring: 
  - "Feature flag usage metrics"
  - "A/B test conversion tracking"
  - "Flag performance impact"
handoff:
  to: "orchestrator"
  next_phase: "Retrospect"
---

# Feature Flags for AnglerHub MVP

## Overview
Feature flags enable safe deployments, gradual rollouts, and A/B testing for AnglerHub while maintaining the $20K MVP budget. This strategy focuses on simple, cost-effective solutions that scale with the platform.

## Implementation Strategy

### Phase 1: Simple Environment-Based Flags (MVP)
Start with configuration-based feature flags using Laravel's config system.

### Phase 2: Dynamic Flags (Growth)
Implement database-driven flags for user-specific toggles and A/B testing.

### Phase 3: Advanced Feature Management (Scale)
Consider third-party services like LaunchDarkly or Unleash for complex scenarios.

## Simple Feature Flag Implementation

### 1. Configuration-Based Flags
Create `config/features.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | This file contains feature flags for controlling application functionality
    | across different environments and deployment phases.
    |
    */

    'weather_integration' => env('FEATURE_WEATHER_INTEGRATION', false),
    'social_sharing' => env('FEATURE_SOCIAL_SHARING', false),
    'advanced_analytics' => env('FEATURE_ADVANCED_ANALYTICS', false),
    'catch_photos' => env('FEATURE_CATCH_PHOTOS', true),
    'goal_reminders' => env('FEATURE_GOAL_REMINDERS', false),
    'fishing_spots_map' => env('FEATURE_FISHING_SPOTS_MAP', false),
    'community_features' => env('FEATURE_COMMUNITY', false),
    'premium_features' => env('FEATURE_PREMIUM', false),
    
    // Percentage-based rollouts
    'new_dashboard_ui' => [
        'enabled' => env('FEATURE_NEW_DASHBOARD_ENABLED', false),
        'percentage' => env('FEATURE_NEW_DASHBOARD_PERCENTAGE', 0),
    ],
    
    'enhanced_catch_form' => [
        'enabled' => env('FEATURE_ENHANCED_CATCH_FORM_ENABLED', false),
        'percentage' => env('FEATURE_ENHANCED_CATCH_FORM_PERCENTAGE', 0),
    ],
];
```

### 2. Feature Flag Service
Create `app/Services/FeatureFlagService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class FeatureFlagService
{
    /**
     * Check if a feature is enabled
     */
    public function isEnabled(string $feature, $user = null): bool
    {
        $user = $user ?? Auth::user();
        
        $flagConfig = config("features.{$feature}");
        
        // Simple boolean flag
        if (is_bool($flagConfig)) {
            return $flagConfig;
        }
        
        // Array-based flag with percentage rollout
        if (is_array($flagConfig)) {
            if (!$flagConfig['enabled']) {
                return false;
            }
            
            // Admin users always get new features
            if ($user && $user->hasRole('admin')) {
                return true;
            }
            
            // Percentage-based rollout
            $percentage = $flagConfig['percentage'] ?? 0;
            if ($percentage >= 100) {
                return true;
            }
            
            if ($percentage <= 0) {
                return false;
            }
            
            // Consistent user-based percentage
            $userId = $user ? $user->id : 0;
            return (crc32($feature . $userId) % 100) < $percentage;
        }
        
        return false;
    }
    
    /**
     * Check if a feature is enabled for a specific user
     */
    public function isEnabledForUser(string $feature, $user): bool
    {
        return $this->isEnabled($feature, $user);
    }
    
    /**
     * Get feature flag status for multiple features
     */
    public function getFlags(array $features, $user = null): array
    {
        $user = $user ?? Auth::user();
        $flags = [];
        
        foreach ($features as $feature) {
            $flags[$feature] = $this->isEnabled($feature, $user);
        }
        
        return $flags;
    }
    
    /**
     * Get all feature flags for frontend
     */
    public function getAllFlags($user = null): array
    {
        $user = $user ?? Auth::user();
        $allFeatures = config('features', []);
        $flags = [];
        
        foreach (array_keys($allFeatures) as $feature) {
            $flags[$feature] = $this->isEnabled($feature, $user);
        }
        
        return $flags;
    }
    
    /**
     * Track feature flag usage
     */
    public function trackUsage(string $feature, $user = null): void
    {
        $user = $user ?? Auth::user();
        $userId = $user ? $user->id : 'anonymous';
        
        $key = "feature_usage:{$feature}:" . now()->format('Y-m-d');
        Cache::increment($key);
        
        $userKey = "feature_usage:{$feature}:user:{$userId}:" . now()->format('Y-m-d');
        Cache::increment($userKey);
        
        // Store for 30 days
        Cache::put($key, Cache::get($key, 0), 60 * 60 * 24 * 30);
        Cache::put($userKey, Cache::get($userKey, 0), 60 * 60 * 24 * 30);
    }
}
```

### 3. Blade Directive
Register in `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Support\Facades\Blade;
use App\Services\FeatureFlagService;

public function boot()
{
    Blade::directive('feature', function ($feature) {
        return "<?php if (app('" . FeatureFlagService::class . "')->isEnabled({$feature})): ?>";
    });
    
    Blade::directive('endfeature', function () {
        return "<?php endif; ?>";
    });
    
    Blade::directive('featureelse', function () {
        return "<?php else: ?>";
    });
}
```

### 4. Middleware for Route Protection
Create `app/Http/Middleware/FeatureFlagMiddleware.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\FeatureFlagService;

class FeatureFlagMiddleware
{
    protected FeatureFlagService $featureFlag;
    
    public function __construct(FeatureFlagService $featureFlag)
    {
        $this->featureFlag = $featureFlag;
    }
    
    public function handle(Request $request, Closure $next, string $feature)
    {
        if (!$this->featureFlag->isEnabled($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Feature not available',
                    'code' => 'FEATURE_DISABLED'
                ], 404);
            }
            
            abort(404);
        }
        
        // Track feature usage
        $this->featureFlag->trackUsage($feature);
        
        return $next($request);
    }
}
```

Register in `app/Http/Kernel.php`:
```php
protected $routeMiddleware = [
    // ... other middleware
    'feature' => \App\Http\Middleware\FeatureFlagMiddleware::class,
];
```

## Usage Patterns

### 1. In Blade Templates
```blade
@feature('catch_photos')
    <div class="photo-upload-section">
        <!-- Photo upload UI -->
    </div>
@featureelse
    <p>Photo uploads coming soon!</p>
@endfeature

@feature('weather_integration')
    <div class="weather-widget">
        <!-- Weather information -->
    </div>
@endfeature
```

### 2. In Controllers
```php
use App\Services\FeatureFlagService;

class CatchController extends Controller
{
    public function store(Request $request, FeatureFlagService $features)
    {
        // Validate catch data
        $data = $request->validate([
            'species' => 'required|string',
            'weight' => 'required|numeric',
        ]);
        
        // Conditional validation based on feature
        if ($features->isEnabled('catch_photos')) {
            $request->validate([
                'photo' => 'nullable|image|max:2048'
            ]);
        }
        
        $catch = Catch::create($data);
        
        // Handle photo upload if feature is enabled
        if ($features->isEnabled('catch_photos') && $request->hasFile('photo')) {
            $this->handlePhotoUpload($catch, $request->file('photo'));
        }
        
        return response()->json($catch);
    }
}
```

### 3. In Routes
```php
// Protect entire route groups
Route::group(['middleware' => 'feature:social_sharing'], function () {
    Route::post('/catches/{catch}/share', [CatchController::class, 'share']);
    Route::get('/catches/{catch}/share-link', [CatchController::class, 'getShareLink']);
});

// Protect individual routes
Route::get('/weather', [WeatherController::class, 'index'])
     ->middleware('feature:weather_integration');
```

### 4. In API Responses
```php
public function index(FeatureFlagService $features)
{
    $catches = auth()->user()->catches()->latest()->get();
    
    return response()->json([
        'catches' => $catches,
        'features' => $features->getFlags([
            'catch_photos',
            'social_sharing',
            'weather_integration'
        ])
    ]);
}
```

## Database-Driven Feature Flags (Phase 2)

### 1. Migration for Feature Flags
```php
// database/migrations/xxxx_create_feature_flags_table.php
public function up()
{
    Schema::create('feature_flags', function (Blueprint $table) {
        $table->id();
        $table->string('key')->unique();
        $table->string('name');
        $table->text('description')->nullable();
        $table->boolean('enabled')->default(false);
        $table->integer('percentage')->default(0); // 0-100
        $table->json('conditions')->nullable(); // Additional conditions
        $table->timestamp('starts_at')->nullable();
        $table->timestamp('ends_at')->nullable();
        $table->timestamps();
    });
}
```

### 2. Feature Flag Model
```php
// app/Models/FeatureFlag.php
class FeatureFlag extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'enabled', 
        'percentage', 'conditions', 'starts_at', 'ends_at'
    ];
    
    protected $casts = [
        'enabled' => 'boolean',
        'conditions' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
    
    public function isActive(): bool
    {
        $now = now();
        
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }
        
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }
        
        return $this->enabled;
    }
}
```

### 3. Enhanced Feature Flag Service
```php
// Enhanced version of FeatureFlagService
public function isEnabled(string $feature, $user = null): bool
{
    // Check database first, fall back to config
    $flag = Cache::remember(
        "feature_flag:{$feature}",
        300, // 5 minutes
        fn() => FeatureFlag::where('key', $feature)->first()
    );
    
    if ($flag) {
        return $this->evaluateDatabaseFlag($flag, $user);
    }
    
    // Fall back to config-based flags
    return $this->evaluateConfigFlag($feature, $user);
}

private function evaluateDatabaseFlag(FeatureFlag $flag, $user): bool
{
    if (!$flag->isActive()) {
        return false;
    }
    
    // Check conditions
    if ($flag->conditions) {
        if (!$this->evaluateConditions($flag->conditions, $user)) {
            return false;
        }
    }
    
    // Percentage rollout
    if ($flag->percentage < 100) {
        $userId = $user ? $user->id : 0;
        return (crc32($flag->key . $userId) % 100) < $flag->percentage;
    }
    
    return true;
}
```

## A/B Testing Implementation

### 1. A/B Test Configuration
```php
// config/experiments.php
return [
    'new_catch_form' => [
        'variants' => [
            'control' => 50,    // 50% get original
            'variant_a' => 50,  // 50% get new form
        ],
        'metrics' => ['catch_completion_rate', 'form_abandonment'],
    ],
    
    'dashboard_layout' => [
        'variants' => [
            'original' => 33,
            'compact' => 33,
            'detailed' => 34,
        ],
        'metrics' => ['session_duration', 'page_views'],
    ],
];
```

### 2. A/B Test Service
```php
// app/Services/ABTestService.php
class ABTestService
{
    public function getVariant(string $experiment, $user = null): string
    {
        $user = $user ?? Auth::user();
        $config = config("experiments.{$experiment}");
        
        if (!$config || !isset($config['variants'])) {
            return 'control';
        }
        
        // Consistent assignment based on user ID
        $userId = $user ? $user->id : session()->getId();
        $hash = crc32($experiment . $userId) % 100;
        
        $cumulative = 0;
        foreach ($config['variants'] as $variant => $percentage) {
            $cumulative += $percentage;
            if ($hash < $cumulative) {
                return $variant;
            }
        }
        
        return 'control';
    }
    
    public function track(string $experiment, string $metric, $value = 1, $user = null): void
    {
        $user = $user ?? Auth::user();
        $variant = $this->getVariant($experiment, $user);
        
        $key = "experiment:{$experiment}:{$variant}:{$metric}:" . now()->format('Y-m-d');
        Cache::increment($key, $value);
        
        // Store for 90 days
        Cache::put($key, Cache::get($key, 0), 60 * 60 * 24 * 90);
    }
}
```

## Feature Flag Monitoring

### 1. Usage Metrics Endpoint
```php
// app/Http/Controllers/Admin/FeatureFlagController.php
public function metrics(Request $request)
{
    $period = $request->get('period', 7); // days
    $features = array_keys(config('features', []));
    
    $metrics = [];
    
    foreach ($features as $feature) {
        $usage = [];
        for ($i = 0; $i < $period; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $key = "feature_usage:{$feature}:{$date}";
            $usage[$date] = Cache::get($key, 0);
        }
        
        $metrics[$feature] = [
            'total_usage' => array_sum($usage),
            'daily_usage' => $usage,
            'enabled' => app(FeatureFlagService::class)->isEnabled($feature),
        ];
    }
    
    return response()->json($metrics);
}
```

### 2. Feature Flag Dashboard
Create a simple admin interface for managing feature flags:

```php
// resources/views/admin/feature-flags.blade.php
@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Feature Flags</h1>
    
    @foreach($flags as $key => $config)
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <h5>{{ $key }}</h5>
            <span class="badge badge-{{ $config['enabled'] ? 'success' : 'secondary' }}">
                {{ $config['enabled'] ? 'Enabled' : 'Disabled' }}
            </span>
        </div>
        <div class="card-body">
            @if(is_array($config) && isset($config['percentage']))
                <p>Rollout: {{ $config['percentage'] }}%</p>
                <div class="progress">
                    <div class="progress-bar" style="width: {{ $config['percentage'] }}%"></div>
                </div>
            @endif
            
            <div class="mt-3">
                <strong>Usage (last 7 days):</strong> {{ $metrics[$key]['total_usage'] ?? 0 }}
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection
```

## Rollout Strategy

### 1. Safe Rollout Process
```
1. Internal Testing (0% - Admin users only)
2. Limited Beta (5% - Early adopters)
3. Small Rollout (25% - Representative sample)
4. Large Rollout (75% - Majority of users)
5. Full Release (100% - All users)
```

### 2. Rollback Procedure
```bash
# Emergency rollback via environment variable
php artisan config:cache  # After changing .env

# Or via database (for dynamic flags)
php artisan feature:disable new_dashboard_ui
```

### 3. Gradual Rollout Script
```php
// app/Console/Commands/FeatureRolloutCommand.php
class FeatureRolloutCommand extends Command
{
    protected $signature = 'feature:rollout {feature} {percentage}';
    
    public function handle()
    {
        $feature = $this->argument('feature');
        $percentage = (int) $this->argument('percentage');
        
        // Update environment or database
        $flag = FeatureFlag::where('key', $feature)->first();
        if ($flag) {
            $flag->update(['percentage' => $percentage]);
            $this->info("Feature {$feature} rolled out to {$percentage}%");
        } else {
            $this->error("Feature {$feature} not found");
        }
    }
}
```

## Environment Configuration

### Development (.env)
```env
# Feature flags for development
FEATURE_WEATHER_INTEGRATION=true
FEATURE_SOCIAL_SHARING=true
FEATURE_CATCH_PHOTOS=true
FEATURE_GOAL_REMINDERS=true

# A/B test flags
FEATURE_NEW_DASHBOARD_ENABLED=true
FEATURE_NEW_DASHBOARD_PERCENTAGE=100
```

### Staging (.env)
```env
# Conservative settings for staging
FEATURE_WEATHER_INTEGRATION=true
FEATURE_SOCIAL_SHARING=false
FEATURE_CATCH_PHOTOS=true
FEATURE_GOAL_REMINDERS=false

# Limited A/B testing
FEATURE_NEW_DASHBOARD_ENABLED=true
FEATURE_NEW_DASHBOARD_PERCENTAGE=50
```

### Production (.env)
```env
# Production rollout
FEATURE_WEATHER_INTEGRATION=false  # Not ready yet
FEATURE_SOCIAL_SHARING=false       # Phase 2 feature
FEATURE_CATCH_PHOTOS=true          # MVP core feature
FEATURE_GOAL_REMINDERS=false       # Needs more testing

# Conservative A/B testing
FEATURE_NEW_DASHBOARD_ENABLED=true
FEATURE_NEW_DASHBOARD_PERCENTAGE=10
```

## Cost Analysis

### Simple Implementation (Current)
- **Development Time**: 8-12 hours
- **Maintenance**: ~2 hours/month
- **Infrastructure**: $0 (uses existing Laravel/MySQL)

### Database-Driven Implementation (Growth Phase)
- **Development Time**: 16-24 hours
- **Maintenance**: ~4 hours/month  
- **Infrastructure**: Minimal (uses existing database)

### Third-Party Service (Scale Phase)
- **LaunchDarkly**: $20/month (up to 1000 MAU)
- **Unleash**: Self-hosted (free) or cloud ($50/month)
- **Split.io**: $33/month (starter plan)

## Best Practices

### 1. Feature Flag Hygiene
- Set expiration dates for temporary flags
- Remove flags after full rollout
- Document flag purpose and timeline
- Regular cleanup of unused flags

### 2. Testing Strategy
```php
// tests/Feature/FeatureFlagTest.php
class FeatureFlagTest extends TestCase
{
    public function test_feature_flag_controls_functionality()
    {
        config(['features.catch_photos' => false]);
        
        $response = $this->postJson('/api/catches', [
            'species' => 'Bass',
            'weight' => 2.5,
            'photo' => UploadedFile::fake()->image('catch.jpg')
        ]);
        
        // Should not process photo when flag is disabled
        $this->assertDatabaseMissing('catch_photos', [
            'catch_id' => $response->json('id')
        ]);
    }
}
```

### 3. Performance Considerations
- Cache feature flag states
- Minimize database queries
- Use simple boolean checks in hot paths
- Monitor flag evaluation performance

### 4. Security Considerations
- Don't expose internal flag names to frontend
- Validate user permissions for admin features
- Log significant flag changes
- Secure admin interfaces

This feature flag strategy provides a foundation for safe deployments and gradual feature rollouts while maintaining budget constraints and development simplicity.