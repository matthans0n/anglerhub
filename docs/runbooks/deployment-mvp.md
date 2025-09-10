---
title: "Deployment Notes for AnglerHub MVP"
phase: "Ship"
deployment_steps: 
  - "Set up production server (DigitalOcean/Linode)"
  - "Configure domain and SSL certificates"
  - "Set up production database"
  - "Configure environment variables"
  - "Deploy application using GitHub Actions"
  - "Run database migrations"
  - "Configure monitoring and backups"
monitoring: 
  - "Laravel Telescope for application monitoring"
  - "MySQL slow query log"
  - "Server resource monitoring (CPU, memory, disk)"
  - "Application error tracking with Laravel Log"
  - "Basic uptime monitoring"
handoff:
  to: "orchestrator"
  next_phase: "Retrospect"
---

# AnglerHub MVP Deployment Runbook

## Overview
Cost-effective deployment guide for AnglerHub Laravel backend MVP targeting 200-1000 solo angler users with a $20K budget.

## Production Infrastructure

### Recommended Hosting Stack
**Primary Option: DigitalOcean Droplet**
- **Server**: 2 vCPU, 4GB RAM, 80GB SSD ($24/month)
- **Database**: Managed MySQL 8.0 - Basic plan ($15/month)
- **Domain**: $12/year
- **SSL**: Let's Encrypt (free)
- **Total**: ~$40/month (~$500/year)

**Alternative: Linode/Vultr**
- Similar pricing and specifications
- Consider based on geographic location for better latency

### Server Setup

#### 1. Initial Server Configuration
```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y nginx mysql-client php8.2 php8.2-fpm php8.2-cli php8.2-mysql \
php8.2-xml php8.2-curl php8.2-gd php8.2-mbstring php8.2-zip php8.2-bcmath \
php8.2-tokenizer php8.2-json php8.2-intl unzip git

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js (for frontend assets if needed)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs
```

#### 2. Nginx Configuration
Create `/etc/nginx/sites-available/anglerhub`:

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/anglerhub/public;
    
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";
    
    index index.html index.htm index.php;
    
    charset utf-8;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    
    error_page 404 /index.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site and SSL:
```bash
sudo ln -s /etc/nginx/sites-available/anglerhub /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# Install Certbot for SSL
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

## Environment Configuration

### Production Environment Variables
Create `/var/www/anglerhub/.env`:

```env
APP_NAME="AnglerHub"
APP_ENV=production
APP_KEY=base64:YOUR_32_CHARACTER_KEY_HERE
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=your-managed-db-host
DB_PORT=3306
DB_DATABASE=anglerhub_prod
DB_USERNAME=your-db-user
DB_PASSWORD=your-secure-db-password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum
SANCTUM_STATEFUL_DOMAINS=your-domain.com,www.your-domain.com

# Weather API (if using external service)
WEATHER_API_KEY=your-weather-api-key

# File uploads
MAX_FILE_SIZE=10240
```

### Security Considerations
```bash
# Set proper file permissions
sudo chown -R www-data:www-data /var/www/anglerhub
sudo chmod -R 755 /var/www/anglerhub
sudo chmod -R 775 /var/www/anglerhub/storage
sudo chmod -R 775 /var/www/anglerhub/bootstrap/cache

# Secure sensitive files
sudo chmod 600 /var/www/anglerhub/.env
```

## Deployment Process

### GitHub Actions Deployment
Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
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
    
    - name: Deploy to server
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.HOST }}
        username: ${{ secrets.USERNAME }}
        key: ${{ secrets.KEY }}
        script: |
          cd /var/www/anglerhub
          git pull origin main
          composer install --no-dev --optimize-autoloader
          php artisan migrate --force
          php artisan config:cache
          php artisan route:cache
          php artisan view:cache
          php artisan queue:restart
          sudo systemctl reload php8.2-fpm
```

### Required GitHub Secrets
- `HOST`: Your server IP address
- `USERNAME`: Server username (e.g., root or deploy user)
- `KEY`: Private SSH key for server access

### Manual Deployment Steps
For initial deployment or manual updates:

```bash
# Clone repository
cd /var/www
sudo git clone https://github.com/matthans0n/anglerhub.git
cd anglerhub

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set up environment
sudo cp .env.example .env
sudo nano .env  # Configure production values

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 storage bootstrap/cache
```

## Database Management

### Managed Database Setup (Recommended)
1. Create DigitalOcean Managed MySQL database
2. Configure firewall to allow your server IP
3. Create production database and user
4. Update `.env` with connection details

### Backup Strategy
```bash
# Daily backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME > /backup/anglerhub_$DATE.sql
# Keep only last 7 days
find /backup -name "anglerhub_*.sql" -mtime +7 -delete
```

Add to crontab:
```bash
0 2 * * * /path/to/backup-script.sh
```

## Monitoring and Alerting

### Application Monitoring
1. **Laravel Telescope** (Development/Staging only - disable in production)
2. **Laravel Log Viewer** for error tracking
3. **Custom Health Check Endpoint**

Create `app/Http/Controllers/HealthController.php`:
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function check()
    {
        try {
            // Check database connection
            DB::connection()->getPdo();
            
            // Check storage permissions
            $canWrite = is_writable(storage_path('logs'));
            
            return response()->json([
                'status' => 'healthy',
                'database' => 'connected',
                'storage' => $canWrite ? 'writable' : 'read-only',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 503);
        }
    }
}
```

### Server Monitoring
**Free Options:**
- **UptimeRobot**: Free plan monitors 50 sites every 5 minutes
- **Netdata**: Free open-source real-time monitoring
- **DigitalOcean Monitoring**: Included with droplets

**Basic Server Metrics:**
- CPU usage > 80% for 5 minutes
- Memory usage > 90% for 5 minutes
- Disk usage > 85%
- HTTP response time > 5 seconds

### Log Management
Configure log rotation in `/etc/logrotate.d/anglerhub`:
```
/var/www/anglerhub/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 644 www-data www-data
}
```

## Performance Optimization

### PHP-FPM Tuning
Edit `/etc/php/8.2/fpm/pool.d/www.conf`:
```ini
; For 4GB RAM server with moderate traffic
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 1000
```

### Laravel Optimizations
```bash
# Production optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Queue processing (if using queues)
php artisan queue:work --daemon --tries=3
```

## Scaling Considerations

### Traffic Growth Indicators
- **200-500 users**: Current setup sufficient
- **500-1000 users**: Add Redis for caching
- **1000+ users**: Consider load balancer + multiple app servers

### Horizontal Scaling Path
1. **Phase 1**: Add Redis cache server
2. **Phase 2**: Add application load balancer
3. **Phase 3**: Multiple application servers
4. **Phase 4**: Database read replicas

## Security Checklist

- [ ] SSL certificate installed and auto-renewal configured
- [ ] Firewall configured (UFW or iptables)
- [ ] SSH key-based authentication only
- [ ] Database access restricted to application server
- [ ] Regular security updates scheduled
- [ ] Application secrets properly secured
- [ ] File upload restrictions in place
- [ ] Rate limiting configured for API endpoints

## Rollback Procedure

### Quick Rollback Steps
```bash
cd /var/www/anglerhub
git fetch origin
git reset --hard HEAD~1  # Roll back one commit
composer install --no-dev --optimize-autoloader
php artisan migrate:rollback  # If needed
php artisan config:cache
sudo systemctl reload php8.2-fpm
```

### Database Rollback
```bash
# Restore from backup (replace DATE with actual backup date)
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < /backup/anglerhub_DATE.sql
```

## Cost Breakdown (Annual)

| Service | Monthly Cost | Annual Cost |
|---------|-------------|-------------|
| DigitalOcean Droplet (2GB) | $24 | $288 |
| Managed MySQL Database | $15 | $180 |
| Domain Registration | $1 | $12 |
| SSL Certificate | $0 | $0 (Let's Encrypt) |
| UptimeRobot Monitoring | $0 | $0 (Free tier) |
| **Total** | **$40** | **$480** |

**Remaining Budget**: $19,520 for development, marketing, and additional features.

## Emergency Contacts and Procedures

### Critical Issues
1. **Application Down**: Check health endpoint, review error logs
2. **Database Issues**: Check managed database console, review slow query log
3. **High Traffic**: Monitor server resources, implement rate limiting
4. **Security Incident**: Review access logs, update passwords, contact hosting provider

### Support Resources
- DigitalOcean Support (24/7 for managed services)
- Laravel Documentation: https://laravel.com/docs
- Community: Laravel Discord, Reddit r/laravel

---

## Next Steps After Deployment

1. Set up monitoring alerts
2. Configure automated backups
3. Implement feature flags for future releases
4. Set up staging environment for testing
5. Document user onboarding process