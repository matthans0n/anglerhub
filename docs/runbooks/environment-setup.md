---
title: "Environment Setup Guide for AnglerHub"
phase: "Ship"
deployment_steps: 
  - "Configure development environment"
  - "Set up staging environment"
  - "Prepare production environment"
  - "Document environment differences"
monitoring: 
  - "Environment health checks"
  - "Configuration drift detection"
handoff:
  to: "orchestrator"
  next_phase: "Retrospect"
---

# Environment Setup Guide for AnglerHub

## Overview
Comprehensive guide for setting up development, staging, and production environments for AnglerHub Laravel backend, ensuring consistency while optimizing for each environment's specific needs.

## Environment Strategy

### Development Environment
- **Purpose**: Local development, testing, debugging
- **Focus**: Developer productivity, easy debugging, fast iteration
- **Data**: Seeded test data, safe to reset

### Staging Environment  
- **Purpose**: Pre-production testing, client demos, QA validation
- **Focus**: Production parity, integration testing
- **Data**: Anonymized production data or realistic test data

### Production Environment
- **Purpose**: Live application serving real users
- **Focus**: Performance, security, reliability, monitoring
- **Data**: Real user data with strict backup and security

## Development Environment Setup

### Prerequisites
- PHP 8.2+
- Composer 2.x
- Node.js 18+
- Docker & Docker Compose (for Laravel Sail)
- Git

### Initial Setup
```bash
# Clone repository
git clone https://github.com/matthans0n/anglerhub.git
cd anglerhub

# Install PHP dependencies
composer install

# Install Node.js dependencies (if frontend assets exist)
npm install

# Environment configuration
cp .env.example .env.local
mv .env.local .env

# Generate application key
php artisan key:generate

# Start development environment with Sail
./vendor/bin/sail up -d

# Run database migrations and seeders
./vendor/bin/sail artisan migrate:fresh --seed

# Install Git hooks
chmod +x scripts/setup-git-hooks.sh
./scripts/setup-git-hooks.sh
```

### Development Environment Variables (.env)
```env
# Application
APP_NAME="AnglerHub (Dev)"
APP_ENV=local
APP_KEY=base64:your-generated-key-here
APP_DEBUG=true
APP_URL=http://localhost

# Database (using Sail defaults)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=anglerhub
DB_USERNAME=sail
DB_PASSWORD=password

# Logging (verbose for debugging)
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Cache & Session (simple drivers for development)
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

# Mail (use Mailpit for local testing)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="dev@anglerhub.com"
MAIL_FROM_NAME="${APP_NAME}"

# Feature Flags (all enabled for development)
FEATURE_WEATHER_INTEGRATION=true
FEATURE_SOCIAL_SHARING=true
FEATURE_ADVANCED_ANALYTICS=true
FEATURE_CATCH_PHOTOS=true
FEATURE_GOAL_REMINDERS=true
FEATURE_FISHING_SPOTS_MAP=true
FEATURE_COMMUNITY_FEATURES=true
FEATURE_PREMIUM_FEATURES=true

# A/B Testing (100% rollout for development)
FEATURE_NEW_DASHBOARD_ENABLED=true
FEATURE_NEW_DASHBOARD_PERCENTAGE=100
FEATURE_ENHANCED_CATCH_FORM_ENABLED=true
FEATURE_ENHANCED_CATCH_FORM_PERCENTAGE=100

# External APIs (test keys or mock services)
WEATHER_API_KEY=your-test-api-key
GOOGLE_MAPS_API_KEY=your-test-maps-key

# File Storage (local disk)
FILESYSTEM_DISK=local
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=

# Development Tools
TELESCOPE_ENABLED=true
DEBUGBAR_ENABLED=true
```

### Development Docker Compose Override
Create `docker-compose.override.yml`:

```yaml
version: '3'

services:
  laravel.test:
    volumes:
      - '.:/var/www/html'
    environment:
      XDEBUG_MODE: '${SAIL_XDEBUG_MODE:-off}'
      XDEBUG_CONFIG: '${SAIL_XDEBUG_CONFIG:-client_host=host.docker.internal}'
    ports:
      - '${APP_PORT:-80}:80'
      - '${VITE_PORT:-5173}:${VITE_PORT:-5173}'

  # Add Mailpit for email testing
  mailpit:
    image: 'axllent/mailpit:latest'
    ports:
      - '${FORWARD_MAILPIT_PORT:-1025}:1025'
      - '${FORWARD_MAILPIT_DASHBOARD_PORT:-8025}:8025'

  # Add Redis for caching experiments
  redis:
    image: 'redis:alpine'
    ports:
      - '${FORWARD_REDIS_PORT:-6379}:6379'
    volumes:
      - 'sail-redis:/data'
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      retries: 3
      timeout: 5s

volumes:
  sail-redis:
    driver: local
```

### Development Database Seeding
Create comprehensive seeders for development:

```php
// database/seeders/DevelopmentSeeder.php
class DevelopmentSeeder extends Seeder
{
    public function run()
    {
        // Create test users
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@anglerhub.com',
            'email_verified_at' => now(),
        ]);
        
        $testUser = User::factory()->create([
            'name' => 'Test Angler',
            'email' => 'test@anglerhub.com',
            'email_verified_at' => now(),
        ]);
        
        // Assign roles if using Spatie permissions
        $admin->assignRole('admin');
        $testUser->assignRole('user');
        
        // Create sample data
        User::factory(10)->create();
        
        // Create catches for test user
        Catch::factory(25)->create([
            'user_id' => $testUser->id,
        ]);
        
        // Create goals
        Goal::factory(5)->create([
            'user_id' => $testUser->id,
        ]);
        
        // Create weather logs
        WeatherLog::factory(30)->create();
    }
}
```

### Development Artisan Commands
Add helpful development commands in `app/Console/Commands/`:

```php
// app/Console/Commands/DevResetCommand.php
class DevResetCommand extends Command
{
    protected $signature = 'dev:reset {--fresh : Fresh migration}';
    protected $description = 'Reset development environment';
    
    public function handle()
    {
        if (app()->environment('production')) {
            $this->error('This command cannot be run in production!');
            return 1;
        }
        
        $this->info('Resetting development environment...');
        
        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--seed' => true]);
        } else {
            $this->call('migrate:reset');
            $this->call('migrate', ['--seed' => true]);
        }
        
        $this->call('cache:clear');
        $this->call('config:clear');
        $this->call('route:clear');
        $this->call('view:clear');
        
        $this->info('Development environment reset complete!');
        return 0;
    }
}
```

## Staging Environment Setup

### Infrastructure
**Staging Server Specs** (DigitalOcean):
- 1 vCPU, 2GB RAM, 50GB SSD ($12/month)
- Managed MySQL 8.0 - Basic ($15/month)
- **Total**: $27/month

### Staging Environment Variables (.env)
```env
# Application
APP_NAME="AnglerHub (Staging)"
APP_ENV=staging
APP_KEY=base64:your-staging-key-here
APP_DEBUG=false
APP_URL=https://staging.anglerhub.com

# Database
DB_CONNECTION=mysql
DB_HOST=your-staging-db-host
DB_PORT=3306
DB_DATABASE=anglerhub_staging
DB_USERNAME=staging_user
DB_PASSWORD=secure-staging-password

# Logging (moderate verbosity)
LOG_CHANNEL=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

# Cache & Session (file-based for simplicity)
CACHE_DRIVER=file
SESSION_DRIVER=database
QUEUE_CONNECTION=database

# Mail (use staging SMTP or service)
MAIL_MAILER=smtp
MAIL_HOST=staging-smtp-host
MAIL_PORT=587
MAIL_USERNAME=staging-mail-user
MAIL_PASSWORD=staging-mail-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="staging@anglerhub.com"
MAIL_FROM_NAME="${APP_NAME}"

# Feature Flags (conservative rollout for testing)
FEATURE_WEATHER_INTEGRATION=true
FEATURE_SOCIAL_SHARING=false
FEATURE_ADVANCED_ANALYTICS=true
FEATURE_CATCH_PHOTOS=true
FEATURE_GOAL_REMINDERS=true
FEATURE_FISHING_SPOTS_MAP=false
FEATURE_COMMUNITY_FEATURES=false
FEATURE_PREMIUM_FEATURES=false

# A/B Testing (50% rollout for testing)
FEATURE_NEW_DASHBOARD_ENABLED=true
FEATURE_NEW_DASHBOARD_PERCENTAGE=50
FEATURE_ENHANCED_CATCH_FORM_ENABLED=true
FEATURE_ENHANCED_CATCH_FORM_PERCENTAGE=25

# External APIs (test keys)
WEATHER_API_KEY=your-staging-weather-key
GOOGLE_MAPS_API_KEY=your-staging-maps-key

# File Storage (could be S3 or local)
FILESYSTEM_DISK=local
AWS_ACCESS_KEY_ID=your-staging-key
AWS_SECRET_ACCESS_KEY=your-staging-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=anglerhub-staging

# Disable development tools
TELESCOPE_ENABLED=false
DEBUGBAR_ENABLED=false

# Sanctum
SANCTUM_STATEFUL_DOMAINS=staging.anglerhub.com
SESSION_DOMAIN=staging.anglerhub.com
```

### Staging Deployment Script
Create `scripts/deploy-staging.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

echo "🚀 Deploying to staging environment..."

# Pull latest code
git fetch origin
git reset --hard origin/develop

# Install/update dependencies
composer install --no-dev --optimize-autoloader

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run migrations (non-destructive)
php artisan migrate --force

# Optimize for production-like performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers if running
php artisan queue:restart

# Run health checks
php artisan health:check || echo "⚠️  Health check failed"

echo "✅ Staging deployment complete!"
echo "📍 Available at: https://staging.anglerhub.com"
```

### Staging Data Management
```php
// app/Console/Commands/StagingDataCommand.php
class StagingDataCommand extends Command
{
    protected $signature = 'staging:refresh-data {--anonymize : Anonymize production data}';
    protected $description = 'Refresh staging data';
    
    public function handle()
    {
        if (!app()->environment('staging')) {
            $this->error('This command can only be run in staging!');
            return 1;
        }
        
        $this->info('Refreshing staging data...');
        
        if ($this->option('anonymize')) {
            $this->anonymizeProductionData();
        } else {
            $this->seedRealisticData();
        }
        
        $this->info('Staging data refresh complete!');
        return 0;
    }
    
    private function seedRealisticData()
    {
        $this->call('migrate:fresh');
        
        // Create realistic test data
        User::factory(50)->create();
        Catch::factory(500)->create();
        Goal::factory(100)->create();
    }
    
    private function anonymizeProductionData()
    {
        // Implementation for anonymizing production data
        // This would typically involve database dumps and anonymization
        $this->info('Anonymizing production data...');
    }
}
```

## Production Environment Setup

### Infrastructure Requirements
**Production Server** (DigitalOcean):
- 2 vCPU, 4GB RAM, 80GB SSD ($24/month)
- Managed MySQL 8.0 - Basic ($15/month)
- Load Balancer (if needed later): $10/month
- **Total**: $39/month (scalable)

### Production Environment Variables (.env)
```env
# Application
APP_NAME="AnglerHub"
APP_ENV=production
APP_KEY=base64:your-production-key-here
APP_DEBUG=false
APP_URL=https://anglerhub.com

# Database
DB_CONNECTION=mysql
DB_HOST=your-production-db-host
DB_PORT=3306
DB_DATABASE=anglerhub_production
DB_USERNAME=production_user
DB_PASSWORD=very-secure-production-password

# Logging (errors and critical only)
LOG_CHANNEL=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Cache & Session (optimized)
CACHE_DRIVER=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=redis-production-password
REDIS_PORT=6379

# Mail (production SMTP service)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@anglerhub.com
MAIL_PASSWORD=production-mail-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@anglerhub.com"
MAIL_FROM_NAME="${APP_NAME}"

# Feature Flags (conservative production rollout)
FEATURE_WEATHER_INTEGRATION=false
FEATURE_SOCIAL_SHARING=false
FEATURE_ADVANCED_ANALYTICS=false
FEATURE_CATCH_PHOTOS=true
FEATURE_GOAL_REMINDERS=false
FEATURE_FISHING_SPOTS_MAP=false
FEATURE_COMMUNITY_FEATURES=false
FEATURE_PREMIUM_FEATURES=false

# A/B Testing (conservative rollout)
FEATURE_NEW_DASHBOARD_ENABLED=true
FEATURE_NEW_DASHBOARD_PERCENTAGE=10
FEATURE_ENHANCED_CATCH_FORM_ENABLED=false
FEATURE_ENHANCED_CATCH_FORM_PERCENTAGE=0

# External APIs (production keys)
WEATHER_API_KEY=your-production-weather-key
GOOGLE_MAPS_API_KEY=your-production-maps-key

# File Storage (S3 for production)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-production-access-key
AWS_SECRET_ACCESS_KEY=your-production-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=anglerhub-production

# Security
SANCTUM_STATEFUL_DOMAINS=anglerhub.com,www.anglerhub.com
SESSION_DOMAIN=anglerhub.com
SESSION_SECURE_COOKIE=true

# Disable all development tools
TELESCOPE_ENABLED=false
DEBUGBAR_ENABLED=false

# Performance optimizations
SESSION_LIFETIME=120
QUEUE_RETRY_AFTER=90
```

### Production Optimization Configuration

#### PHP-FPM Configuration (`/etc/php/8.2/fpm/pool.d/www.conf`)
```ini
; Optimize for 4GB RAM server
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 1000

; Performance tuning
pm.process_idle_timeout = 30s
pm.max_requests = 1000

; Logging
php_admin_value[error_log] = /var/log/php8.2-fpm.log
php_admin_flag[log_errors] = on

; Memory limits
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 30
```

#### Nginx Configuration (`/etc/nginx/sites-available/anglerhub`)
```nginx
server {
    listen 443 ssl http2;
    server_name anglerhub.com www.anglerhub.com;
    root /var/www/anglerhub/public;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/anglerhub.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/anglerhub.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Performance
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;
    
    # File caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    index index.php;
    
    charset utf-8;
    
    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    
    error_page 404 /index.php;
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Performance tweaks
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name anglerhub.com www.anglerhub.com;
    return 301 https://$server_name$request_uri;
}
```

### Production Deployment Pipeline
Create `.github/workflows/production-deploy.yml`:

```yaml
name: Production Deployment

on:
  push:
    branches: [main]
    tags: ['v*']

jobs:
  tests:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: secret
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
    
    - name: Install dependencies
      run: composer install --no-dev --optimize-autoloader
    
    - name: Run tests
      run: ./vendor/bin/pest
      env:
        DB_CONNECTION: mysql
        DB_HOST: 127.0.0.1
        DB_PORT: 3306
        DB_DATABASE: testing
        DB_USERNAME: root
        DB_PASSWORD: secret

  deploy:
    needs: tests
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
    - name: Deploy to production
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.PRODUCTION_HOST }}
        username: ${{ secrets.PRODUCTION_USER }}
        key: ${{ secrets.PRODUCTION_SSH_KEY }}
        script: |
          cd /var/www/anglerhub
          git pull origin main
          composer install --no-dev --optimize-autoloader
          php artisan migrate --force
          php artisan config:cache
          php artisan route:cache
          php artisan view:cache
          php artisan queue:restart
          sudo systemctl reload php8.2-fpm nginx
          
    - name: Notify deployment
      if: always()
      run: |
        # Send deployment notification (email, Slack, etc.)
        echo "Deployment completed"
```

## Environment Comparison Matrix

| Aspect | Development | Staging | Production |
|--------|-------------|---------|------------|
| **App Debug** | `true` | `false` | `false` |
| **Log Level** | `debug` | `info` | `error` |
| **Cache Driver** | `file` | `file` | `redis` |
| **Queue Driver** | `database` | `database` | `redis` |
| **Mail Driver** | `mailpit` | `smtp` | `smtp` |
| **File Storage** | `local` | `local/s3` | `s3` |
| **Feature Flags** | All enabled | Conservative | Very conservative |
| **SSL** | No | Yes | Yes |
| **Monitoring** | Telescope | Basic | Comprehensive |
| **Backups** | None | Daily | Hourly |
| **Resources** | 2GB RAM | 2GB RAM | 4GB RAM |

## Environment Switching Commands

Create helper commands for managing environments:

```php
// app/Console/Commands/EnvSwitchCommand.php
class EnvSwitchCommand extends Command
{
    protected $signature = 'env:switch {environment : The environment to switch to}';
    protected $description = 'Switch between environment configurations';
    
    public function handle()
    {
        $env = $this->argument('environment');
        $envFile = base_path(".env.{$env}");
        
        if (!file_exists($envFile)) {
            $this->error("Environment file .env.{$env} not found!");
            return 1;
        }
        
        copy($envFile, base_path('.env'));
        
        $this->call('config:clear');
        $this->call('cache:clear');
        
        $this->info("Switched to {$env} environment");
        return 0;
    }
}
```

## Health Checks per Environment

### Development Health Check
```bash
#!/bin/bash
# scripts/health-check-dev.sh

echo "🔍 Development Environment Health Check"

# Check if Sail is running
if ! docker-compose ps | grep -q "Up"; then
    echo "❌ Docker containers not running"
    exit 1
fi

# Check database connection
php artisan tinker --execute="DB::connection()->getPdo();"
echo "✅ Database connection OK"

# Check if seeders ran
USER_COUNT=$(php artisan tinker --execute="echo User::count();")
if [ "$USER_COUNT" -lt 5 ]; then
    echo "⚠️  Low user count, consider running seeders"
fi

echo "✅ Development environment healthy"
```

### Production Health Check
```bash
#!/bin/bash
# scripts/health-check-prod.sh

echo "🔍 Production Environment Health Check"

# Check web server
if ! systemctl is-active --quiet nginx; then
    echo "❌ Nginx not running"
    exit 1
fi

# Check PHP-FPM
if ! systemctl is-active --quiet php8.2-fpm; then
    echo "❌ PHP-FPM not running"
    exit 1
fi

# Check database connection
php artisan health:check
echo "✅ Production environment healthy"
```

## Troubleshooting Guide

### Common Environment Issues

**1. Database Connection Issues**
```bash
# Check database credentials
php artisan tinker --execute="config('database.connections.mysql');"

# Test connection
php artisan migrate:status
```

**2. Permission Problems**
```bash
# Fix Laravel permissions
sudo chown -R www-data:www-data /var/www/anglerhub
sudo chmod -R 755 /var/www/anglerhub
sudo chmod -R 775 /var/www/anglerhub/storage
sudo chmod -R 775 /var/www/anglerhub/bootstrap/cache
```

**3. Cache Issues**
```bash
# Clear all caches
php artisan optimize:clear

# Or individually
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

**4. Environment Variable Issues**
```bash
# Check loaded environment
php artisan about

# Validate specific config
php artisan tinker --execute="config('app.env');"
```

This comprehensive environment setup guide ensures consistent, secure, and optimized deployments across all stages of the AnglerHub development lifecycle.