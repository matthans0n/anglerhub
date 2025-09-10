---
title: "Monitoring and Alerting for AnglerHub MVP"
phase: "Ship"
deployment_steps: 
  - "Configure application health checks"
  - "Set up uptime monitoring"
  - "Configure error tracking and logging"
  - "Implement basic server monitoring"
monitoring: 
  - "Laravel health check endpoint"
  - "UptimeRobot for uptime monitoring"
  - "Laravel Log for error tracking"
  - "Server resource monitoring"
  - "Database performance monitoring"
handoff:
  to: "orchestrator"
  next_phase: "Retrospect"
---

# AnglerHub MVP Monitoring and Alerting

## Overview
Cost-effective monitoring strategy for a solo angler platform with 200-1000 users, focusing on essential metrics while staying within the $20K MVP budget.

## Monitoring Stack (Budget-Friendly)

### Free Tier Services
1. **UptimeRobot** - Free plan (50 monitors, 5-minute intervals)
2. **Laravel Log** - Built-in error tracking
3. **DigitalOcean Monitoring** - Included with droplets
4. **Netdata** - Open-source real-time monitoring

### Total Monthly Cost: $0-15 (mostly free tier usage)

## Application Monitoring

### Health Check Endpoint
Create comprehensive health checks in `app/Http/Controllers/HealthController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function check()
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];
        
        $isHealthy = collect($checks)->every(fn($check) => $check['status'] === 'ok');
        
        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
        ], $isHealthy ? 200 : 503);
    }
    
    public function quick()
    {
        // Lightweight check for frequent monitoring
        try {
            DB::connection()->getPdo();
            return response()->json(['status' => 'ok'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error'], 503);
        }
    }
    
    private function checkDatabase()
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000, 2);
            
            return [
                'status' => 'ok',
                'response_time_ms' => $responseTime,
                'message' => 'Database connection successful'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkStorage()
    {
        try {
            $testFile = 'health-check-' . time() . '.txt';
            Storage::put($testFile, 'health check');
            $canRead = Storage::exists($testFile);
            Storage::delete($testFile);
            
            $diskFree = disk_free_space(storage_path());
            $diskTotal = disk_total_space(storage_path());
            $diskUsagePercent = round((($diskTotal - $diskFree) / $diskTotal) * 100, 2);
            
            return [
                'status' => $diskUsagePercent > 90 ? 'warning' : 'ok',
                'disk_usage_percent' => $diskUsagePercent,
                'message' => $canRead ? 'Storage is writable' : 'Storage read/write failed'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Storage check failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkCache()
    {
        try {
            $key = 'health-check-' . time();
            Cache::put($key, 'test', 60);
            $canRead = Cache::get($key) === 'test';
            Cache::forget($key);
            
            return [
                'status' => $canRead ? 'ok' : 'error',
                'message' => $canRead ? 'Cache is working' : 'Cache read/write failed'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cache check failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkQueue()
    {
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            $pendingJobs = DB::table('jobs')->count();
            
            return [
                'status' => $failedJobs > 10 ? 'warning' : 'ok',
                'failed_jobs' => $failedJobs,
                'pending_jobs' => $pendingJobs,
                'message' => 'Queue status checked'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Queue check failed: ' . $e->getMessage()
            ];
        }
    }
}
```

Add routes in `routes/api.php`:
```php
Route::get('/health', [HealthController::class, 'check']);
Route::get('/health/quick', [HealthController::class, 'quick']);
```

### Application Metrics Middleware
Create `app/Http/Middleware/MetricsMiddleware.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MetricsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        
        $response = $next($request);
        
        $duration = microtime(true) - $start;
        
        // Log slow requests (>2 seconds)
        if ($duration > 2.0) {
            Log::warning('Slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'duration_ms' => round($duration * 1000, 2),
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);
        }
        
        // Track API metrics
        if ($request->is('api/*')) {
            $this->trackApiMetrics($request, $response, $duration);
        }
        
        return $response;
    }
    
    private function trackApiMetrics(Request $request, $response, float $duration)
    {
        $date = now()->format('Y-m-d');
        $endpoint = $request->route()?->getName() ?? 'unknown';
        
        // Increment daily counters
        Cache::increment("metrics:{$date}:requests");
        Cache::increment("metrics:{$date}:endpoint:{$endpoint}");
        
        if ($response->status() >= 400) {
            Cache::increment("metrics:{$date}:errors");
            Cache::increment("metrics:{$date}:endpoint:{$endpoint}:errors");
        }
        
        // Track response times (store last 100 values)
        $responseTimeKey = "metrics:{$date}:response_times";
        $responseTimes = Cache::get($responseTimeKey, []);
        $responseTimes[] = round($duration * 1000, 2);
        
        if (count($responseTimes) > 100) {
            array_shift($responseTimes);
        }
        
        Cache::put($responseTimeKey, $responseTimes, 60 * 60 * 25); // 25 hours
    }
}
```

## Uptime Monitoring

### UptimeRobot Configuration
**Free Plan Monitors (50 total):**

1. **Primary Health Check** - `https://your-domain.com/api/health/quick`
   - Interval: 5 minutes
   - Timeout: 30 seconds
   - Expected: HTTP 200

2. **Full Health Check** - `https://your-domain.com/api/health`
   - Interval: 30 minutes
   - Timeout: 60 seconds
   - Expected: HTTP 200 with "healthy" in response

3. **Homepage Check** - `https://your-domain.com`
   - Interval: 5 minutes
   - Timeout: 30 seconds
   - Expected: HTTP 200

4. **API Endpoint Checks**:
   - Login endpoint
   - User registration
   - Catch logging
   - Goals endpoint

### Alert Configuration
**Email Alerts** (Free):
- Down for 2 consecutive checks
- Up after being down

**SMS Alerts** (Paid - $4/month for essential alerts):
- Only for critical failures
- Primary health check down for 10+ minutes

## Error Tracking and Logging

### Laravel Log Configuration
Update `config/logging.php` for production:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],
        'ignore_exceptions' => false,
    ],
    
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'error'),
        'days' => 14,
    ],
    
    'errors' => [
        'driver' => 'daily',
        'path' => storage_path('logs/errors.log'),
        'level' => 'error',
        'days' => 30,
    ],
],
```

### Error Notification Command
Create `app/Console/Commands/CheckErrorsCommand.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

class CheckErrorsCommand extends Command
{
    protected $signature = 'errors:check';
    protected $description = 'Check for recent errors and send alerts';
    
    public function handle()
    {
        $logFile = storage_path('logs/laravel-' . now()->format('Y-m-d') . '.log');
        
        if (!File::exists($logFile)) {
            return 0;
        }
        
        $logContent = File::get($logFile);
        $errorCount = substr_count($logContent, '.ERROR:');
        $criticalCount = substr_count($logContent, '.CRITICAL:');
        
        if ($errorCount > 10 || $criticalCount > 0) {
            $this->sendErrorAlert($errorCount, $criticalCount);
        }
        
        return 0;
    }
    
    private function sendErrorAlert($errorCount, $criticalCount)
    {
        // Send simple email or webhook notification
        // Implementation depends on your notification preferences
        $this->error("High error rate detected: {$errorCount} errors, {$criticalCount} critical");
    }
}
```

Schedule in `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('errors:check')->everyFifteenMinutes();
}
```

## Server Monitoring

### DigitalOcean Monitoring (Free)
**Enabled Metrics:**
- CPU usage
- Memory usage  
- Disk usage
- Network I/O
- Load average

**Alert Thresholds:**
- CPU > 80% for 10 minutes
- Memory > 90% for 5 minutes  
- Disk usage > 85%
- Load average > 2.0 for 15 minutes

### Netdata Installation (Optional)
```bash
# Install Netdata for real-time monitoring
bash <(curl -Ss https://my-netdata.io/kickstart.sh)

# Configure basic authentication
sudo /etc/netdata/edit-config go.d/httpcheck.conf
```

## Database Monitoring

### MySQL Performance Monitoring
Enable slow query log in MySQL configuration:

```sql
-- Enable slow query logging
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;
SET GLOBAL log_queries_not_using_indexes = 'ON';
```

### Database Health Check Command
Create `app/Console/Commands/DatabaseHealthCommand.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseHealthCommand extends Command
{
    protected $signature = 'db:health';
    protected $description = 'Check database health and performance';
    
    public function handle()
    {
        $checks = [
            'connection' => $this->checkConnection(),
            'slow_queries' => $this->checkSlowQueries(),
            'table_sizes' => $this->checkTableSizes(),
        ];
        
        foreach ($checks as $check => $result) {
            $status = $result['status'] === 'ok' ? '✅' : '❌';
            $this->line("{$status} {$check}: {$result['message']}");
        }
        
        return 0;
    }
    
    private function checkConnection()
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $duration = round((microtime(true) - $start) * 1000, 2);
            
            return [
                'status' => $duration < 100 ? 'ok' : 'warning',
                'message' => "Connection time: {$duration}ms"
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    private function checkSlowQueries()
    {
        try {
            $result = DB::select("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
            $slowQueries = $result[0]->Value ?? 0;
            
            return [
                'status' => $slowQueries < 10 ? 'ok' : 'warning',
                'message' => "Slow queries: {$slowQueries}"
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Could not check slow queries'
            ];
        }
    }
    
    private function checkTableSizes()
    {
        try {
            $tables = DB::select("
                SELECT table_name, 
                       ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE()
                ORDER BY size_mb DESC
            ");
            
            $totalSize = array_sum(array_column($tables, 'size_mb'));
            
            return [
                'status' => $totalSize < 1000 ? 'ok' : 'warning', // 1GB threshold
                'message' => "Total database size: {$totalSize}MB"
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Could not check table sizes'
            ];
        }
    }
}
```

## Custom Metrics Dashboard

### Simple Metrics Endpoint
Create `app/Http/Controllers/MetricsController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    public function index()
    {
        $date = now()->format('Y-m-d');
        
        $metrics = [
            'requests_today' => Cache::get("metrics:{$date}:requests", 0),
            'errors_today' => Cache::get("metrics:{$date}:errors", 0),
            'active_users' => $this->getActiveUsers(),
            'catches_logged' => $this->getCatchesToday(),
            'response_times' => $this->getAverageResponseTime($date),
        ];
        
        return response()->json($metrics);
    }
    
    private function getActiveUsers()
    {
        return DB::table('personal_access_tokens')
            ->where('last_used_at', '>=', now()->subHour())
            ->distinct('tokenable_id')
            ->count();
    }
    
    private function getCatchesToday()
    {
        return DB::table('catches')
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }
    
    private function getAverageResponseTime($date)
    {
        $responseTimes = Cache::get("metrics:{$date}:response_times", []);
        
        if (empty($responseTimes)) {
            return null;
        }
        
        return [
            'average' => round(array_sum($responseTimes) / count($responseTimes), 2),
            'max' => max($responseTimes),
            'min' => min($responseTimes),
        ];
    }
}
```

## Alert Escalation Matrix

### Severity Levels

**P1 - Critical (Immediate Response)**
- Application completely down
- Database connection lost
- Security breach detected
- **Response Time**: 15 minutes
- **Notification**: SMS + Email

**P2 - High (Response within 1 hour)**
- High error rate (>50 errors/hour)
- Slow response times (>5s average)
- Disk space >90%
- **Response Time**: 1 hour
- **Notification**: Email

**P3 - Medium (Response within 4 hours)**
- Minor performance degradation
- Non-critical feature failures
- **Response Time**: 4 hours
- **Notification**: Email (batched)

**P4 - Low (Response within 24 hours)**
- Minor UI issues
- Non-urgent maintenance needed
- **Response Time**: Next business day
- **Notification**: Dashboard only

### Notification Channels

1. **Email** (Primary - Free)
   - All severity levels
   - Detailed error information
   - Trend analysis

2. **SMS** (Critical only - $4/month)
   - P1 incidents only
   - Concise alert messages
   - Escalation after 15 minutes

3. **Dashboard** (All levels - Free)
   - Real-time metrics
   - Historical trends
   - Self-service diagnostics

## Monitoring Checklist

### Daily Checks (Automated)
- [ ] Application health check
- [ ] Error rate within thresholds
- [ ] Database performance
- [ ] Disk space usage
- [ ] Backup completion

### Weekly Checks (Manual)
- [ ] Review slow query log
- [ ] Check system resource trends
- [ ] Review user activity patterns
- [ ] Test alert notifications
- [ ] Update monitoring thresholds

### Monthly Checks (Manual)
- [ ] Review and adjust alert thresholds
- [ ] Analyze traffic patterns for scaling
- [ ] Update monitoring documentation
- [ ] Test disaster recovery procedures
- [ ] Review monitoring costs

## Budget Breakdown

| Service | Cost | Description |
|---------|------|-------------|
| UptimeRobot | $0 | Free tier (50 monitors) |
| SMS Alerts | $4/month | Critical alerts only |
| DigitalOcean Monitoring | $0 | Included with droplet |
| Laravel Log | $0 | Built-in logging |
| **Total Monthly** | **$4** | **$48/year** |

**Annual monitoring cost**: $48 (0.24% of $20K budget)

## Future Scaling Considerations

### When to upgrade monitoring (500+ users):
1. **Paid UptimeRobot Plan** ($7/month)
   - 1-minute intervals
   - More monitors
   - Advanced reporting

2. **Application Performance Monitoring**
   - New Relic (free tier available)
   - Laravel Telescope (staging only)

3. **Log Management**
   - Centralized logging with ELK stack
   - Log aggregation across multiple servers

### Growth Indicators to Monitor:
- Average response time trending upward
- Error rate increasing
- Database query performance degrading
- User session duration patterns
- API endpoint usage patterns

---

This monitoring setup provides comprehensive coverage for an MVP while maintaining cost efficiency and focusing on the metrics that matter most for a solo angler platform.