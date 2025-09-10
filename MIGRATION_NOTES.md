# Migration Notes

## Database Setup and Environment Configuration

This document provides step-by-step guidance for setting up AnglerHub in different environments.

## Fresh Installation

### Prerequisites
- PHP 8.2 or higher
- MySQL 8.0 or higher (or compatible database)
- Composer
- Docker (optional, for Sail-based development)

### Environment Configuration

1. **Copy environment file**
   ```bash
   cp .env.example .env
   ```

2. **Configure database connection**
   Edit `.env` file with your database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=anglerhub
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

3. **Configure application settings**
   ```env
   APP_NAME="AnglerHub"
   APP_ENV=local
   APP_KEY=
   APP_DEBUG=true
   APP_URL=http://localhost
   ```

4. **Generate application key**
   ```bash
   php artisan key:generate
   ```

### Database Migration

1. **Create database** (if using local MySQL)
   ```sql
   CREATE DATABASE anglerhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Run migrations**
   ```bash
   php artisan migrate
   ```

3. **Optional: Seed with sample data**
   ```bash
   php artisan db:seed
   ```

## Docker/Sail Setup

### Quick Start with Sail

1. **Start containers**
   ```bash
   ./vendor/bin/sail up -d
   ```

2. **Generate application key**
   ```bash
   ./vendor/bin/sail artisan key:generate
   ```

3. **Run migrations**
   ```bash
   ./vendor/bin/sail artisan migrate
   ```

### Sail Environment Variables
The following variables control Sail's Docker configuration:
```env
APP_PORT=80
FORWARD_DB_PORT=3306
SAIL_XDEBUG_MODE=develop,debug
WWWGROUP=1000
WWWUSER=1000
```

## Production Deployment

### Environment Configuration

1. **Security settings**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.com
   ```

2. **Database configuration**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=your-db-host
   DB_PORT=3306
   DB_DATABASE=anglerhub_prod
   DB_USERNAME=anglerhub_user
   DB_PASSWORD=secure_password
   ```

3. **Session and cache configuration**
   ```env
   SESSION_DRIVER=redis
   CACHE_DRIVER=redis
   QUEUE_CONNECTION=redis
   REDIS_HOST=your-redis-host
   REDIS_PASSWORD=redis_password
   REDIS_PORT=6379
   ```

### Production Deployment Steps

1. **Install dependencies**
   ```bash
   composer install --optimize-autoloader --no-dev
   ```

2. **Configuration caching**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

3. **Run migrations**
   ```bash
   php artisan migrate --force
   ```

4. **Storage linking**
   ```bash
   php artisan storage:link
   ```

## Database Schema Overview

### Users Table
- Standard Laravel user authentication
- Additional profile fields for angler preferences
- Timezone and unit preferences (metric/imperial)

### Catches Table
- Comprehensive fishing catch data
- GPS coordinates for location tracking
- Weather and environmental conditions
- Photo storage (JSON array of URLs)
- Personal best and release tracking

### Goals Table
- Flexible goal system with JSON criteria
- Progress tracking with automated calculations
- Timeline management (start/target dates)
- Multiple goal types: species, weight, count, location, custom

## Common Issues and Solutions

### Database Connection Issues

1. **MySQL connection refused**
   - Verify MySQL service is running
   - Check connection credentials in `.env`
   - Ensure database exists

2. **Sail database connection**
   - Use `mysql` as DB_HOST when running with Sail
   - Database port 3306 is forwarded to host machine

### Migration Issues

1. **Foreign key constraint errors**
   - Ensure proper migration order
   - Check if referenced tables exist
   - Verify foreign key relationships

2. **Column already exists**
   - Review migration history: `php artisan migrate:status`
   - Consider migration rollback and re-run

### Permission Issues

1. **Storage directory permissions**
   ```bash
   chmod -R 755 storage/
   chmod -R 755 bootstrap/cache/
   ```

2. **Sail file permissions**
   - Ensure WWWUSER and WWWGROUP match your system user
   - Consider running: `./vendor/bin/sail artisan storage:link`

## Testing Setup

### Test Database Configuration

Add to `.env.testing`:
```env
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

Or use separate MySQL test database:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=anglerhub_test
DB_USERNAME=test_user
DB_PASSWORD=test_password
```

### Running Tests
```bash
# All tests
./vendor/bin/pest

# Specific test suite
./vendor/bin/pest --testsuite=Feature

# With coverage
./vendor/bin/pest --coverage
```

## Backup and Maintenance

### Database Backup
```bash
# Create backup
mysqldump -u username -p anglerhub > anglerhub_backup.sql

# Restore from backup
mysql -u username -p anglerhub < anglerhub_backup.sql
```

### Maintenance Commands
```bash
# Clear application cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Queue maintenance (if using queues)
php artisan queue:restart

# Storage cleanup
php artisan storage:link --force
```

---

## Support

For issues with setup or deployment:
1. Check the main README.md for detailed documentation
2. Review Laravel documentation for framework-specific issues
3. Consult deployment runbooks in `docs/runbooks/`
4. Open an issue on the GitHub repository