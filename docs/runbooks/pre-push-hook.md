---
title: "Pre-Push Hook Setup for AnglerHub"
phase: "Ship"
deployment_steps: 
  - "Install pre-push hook in .git/hooks/"
  - "Make hook executable"
  - "Test hook functionality"
monitoring: 
  - "Hook execution logging"
  - "Test failure notifications"
handoff:
  to: "orchestrator"
  next_phase: "Retrospect"
---

# Pre-Push Hook for AnglerHub

## Overview
A pre-push hook that ensures code quality by running linting, tests, and basic security checks before allowing pushes to remote repositories. This prevents broken code from reaching production.

## Hook Installation

### 1. Create the Pre-Push Hook
Create the file `.git/hooks/pre-push`:

```bash
#!/usr/bin/env bash
set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}[pre-push] Starting quality checks...${NC}"

# Function to log and exit on failure
fail_check() {
    echo -e "${RED}[pre-push] ❌ $1${NC}"
    echo -e "${RED}[pre-push] Push blocked. Please fix the issues above.${NC}"
    exit 1
}

# Function for success messages
success_check() {
    echo -e "${GREEN}[pre-push] ✅ $1${NC}"
}

# Function for warning messages
warn_check() {
    echo -e "${YELLOW}[pre-push] ⚠️  $1${NC}"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    fail_check "Not in Laravel project root directory"
fi

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}[pre-push] Installing dependencies...${NC}"
    composer install --no-dev --optimize-autoloader || fail_check "Composer install failed"
fi

# 1. PHP Syntax Check
echo -e "${BLUE}[pre-push] Checking PHP syntax...${NC}"
find app/ -name "*.php" -exec php -l {} \; > /dev/null 2>&1 || fail_check "PHP syntax errors found"
success_check "PHP syntax check passed"

# 2. Laravel Pint (Code Style)
echo -e "${BLUE}[pre-push] Running Laravel Pint (code style)...${NC}"
if [ -f "vendor/bin/pint" ]; then
    ./vendor/bin/pint --test || fail_check "Code style violations found. Run './vendor/bin/pint' to fix."
    success_check "Code style check passed"
else
    warn_check "Laravel Pint not found, skipping code style check"
fi

# 3. PHPStan (Static Analysis) - if installed
echo -e "${BLUE}[pre-push] Running static analysis...${NC}"
if [ -f "vendor/bin/phpstan" ]; then
    ./vendor/bin/phpstan analyse --memory-limit=1G || fail_check "Static analysis failed"
    success_check "Static analysis passed"
else
    warn_check "PHPStan not found, skipping static analysis"
fi

# 4. Unit Tests
echo -e "${BLUE}[pre-push] Running unit tests...${NC}"
if [ -f "vendor/bin/pest" ]; then
    ./vendor/bin/pest --testsuite=unit --stop-on-failure || fail_check "Unit tests failed"
    success_check "Unit tests passed"
elif [ -f "vendor/bin/phpunit" ]; then
    ./vendor/bin/phpunit --testsuite=Unit --stop-on-failure || fail_check "Unit tests failed"
    success_check "Unit tests passed"
else
    fail_check "No test runner found (Pest or PHPUnit)"
fi

# 5. Feature Tests
echo -e "${BLUE}[pre-push] Running feature tests...${NC}"
if [ -f "vendor/bin/pest" ]; then
    ./vendor/bin/pest --testsuite=feature --stop-on-failure || fail_check "Feature tests failed"
    success_check "Feature tests passed"
elif [ -f "vendor/bin/phpunit" ]; then
    ./vendor/bin/phpunit --testsuite=Feature --stop-on-failure || fail_check "Feature tests failed"
    success_check "Feature tests passed"
else
    warn_check "Feature test suite not found"
fi

# 6. Check for common security issues
echo -e "${BLUE}[pre-push] Checking for security issues...${NC}"

# Check for debug statements
if grep -r "dd(" app/ || grep -r "dump(" app/ || grep -r "var_dump(" app/; then
    fail_check "Debug statements found in code (dd, dump, var_dump)"
fi

# Check for hardcoded passwords or secrets
if grep -ri "password.*=" app/ | grep -v "\$password" | grep -v "Hash::" | grep -v "bcrypt" | grep -v "password_" | head -1; then
    fail_check "Potential hardcoded password found"
fi

# Check for exposed API keys (basic patterns)
if grep -r "sk_live_\|sk_test_\|AKIA\|AIza" app/ config/; then
    fail_check "Potential API key exposed in code"
fi

success_check "Security checks passed"

# 7. Configuration validation
echo -e "${BLUE}[pre-push] Validating configuration...${NC}"

# Check if .env.example is up to date (basic check)
if [ -f ".env.example" ] && [ -f ".env" ]; then
    env_example_keys=$(grep "^[A-Z]" .env.example | cut -d= -f1 | sort)
    env_keys=$(grep "^[A-Z]" .env | cut -d= -f1 | sort)
    
    if ! diff -q <(echo "$env_example_keys") <(echo "$env_keys") > /dev/null; then
        warn_check ".env and .env.example may be out of sync"
    else
        success_check "Environment configuration check passed"
    fi
fi

# 8. Database migration check
echo -e "${BLUE}[pre-push] Checking database migrations...${NC}"
if [ -d "database/migrations" ]; then
    # Basic check for migration syntax
    php artisan migrate:status --env=testing > /dev/null 2>&1 || fail_check "Migration status check failed"
    success_check "Database migration check passed"
fi

# 9. Route validation
echo -e "${BLUE}[pre-push] Validating routes...${NC}"
php artisan route:list > /dev/null 2>&1 || fail_check "Route validation failed"
success_check "Route validation passed"

# 10. Check for large files that shouldn't be committed
echo -e "${BLUE}[pre-push] Checking for large files...${NC}"
large_files=$(find . -name "*.log" -o -name "*.sql" -o -name "*.dump" -o -name "node_modules" -prune -o -type f -size +1M -print | head -5)
if [ ! -z "$large_files" ]; then
    warn_check "Large files detected (may not be appropriate for git):"
    echo "$large_files"
fi

# 11. Performance check (quick)
echo -e "${BLUE}[pre-push] Running performance checks...${NC}"

# Check for N+1 queries in obvious places (basic regex check)
if grep -r "foreach.*->.*->" app/ | head -1; then
    warn_check "Potential N+1 query pattern detected (review with lazy loading)"
fi

success_check "Performance checks completed"

# 12. Final validation
echo -e "${BLUE}[pre-push] Running final validation...${NC}"

# Clear and rebuild caches to ensure everything works
php artisan config:clear > /dev/null 2>&1
php artisan route:clear > /dev/null 2>&1
php artisan view:clear > /dev/null 2>&1

# Test that the application can boot
php artisan about > /dev/null 2>&1 || fail_check "Application failed to boot properly"

success_check "Application boot test passed"

# Summary
echo -e "${GREEN}[pre-push] ✅ All checks passed! Proceeding with push...${NC}"
echo -e "${BLUE}[pre-push] Quality gates completed in $(date)${NC}"

exit 0
```

### 2. Make the Hook Executable
```bash
chmod +x .git/hooks/pre-push
```

### 3. Test the Hook
```bash
# Test without pushing
git push --dry-run origin main
```

## Lightweight Version (Fast)

For faster commits, create `.git/hooks/pre-push-fast`:

```bash
#!/usr/bin/env bash
set -euo pipefail

echo "[pre-push-fast] Running essential checks..."

# Quick syntax check
find app/ -name "*.php" -exec php -l {} \; > /dev/null 2>&1 || {
    echo "❌ PHP syntax errors found"
    exit 1
}

# Run only unit tests (faster)
if [ -f "vendor/bin/pest" ]; then
    ./vendor/bin/pest --testsuite=unit --stop-on-failure || {
        echo "❌ Unit tests failed"
        exit 1
    }
fi

# Basic security check
if grep -r "dd(" app/ || grep -r "dump(" app/; then
    echo "❌ Debug statements found"
    exit 1
fi

echo "✅ Fast checks passed!"
exit 0
```

## Configuration Options

### Skip Hook Temporarily
```bash
# Skip pre-push hook for emergency pushes
git push --no-verify origin main
```

### Conditional Hook Execution
Add to the top of your hook:

```bash
# Skip on specific branches
current_branch=$(git branch --show-current)
if [[ "$current_branch" == "hotfix/"* ]]; then
    echo "[pre-push] Skipping checks for hotfix branch"
    exit 0
fi

# Skip for specific commit messages
last_commit_msg=$(git log -1 --pretty=%B)
if [[ "$last_commit_msg" == *"[skip-hooks]"* ]]; then
    echo "[pre-push] Skipping checks due to [skip-hooks] in commit message"
    exit 0
fi
```

## Team Setup Script

Create `scripts/setup-git-hooks.sh`:

```bash
#!/usr/bin/env bash

echo "Setting up Git hooks for AnglerHub..."

# Create hooks directory if it doesn't exist
mkdir -p .git/hooks

# Copy pre-push hook
cp scripts/pre-push .git/hooks/pre-push
chmod +x .git/hooks/pre-push

# Optional: Set up pre-commit hook for immediate feedback
if [ "$1" == "--with-pre-commit" ]; then
    cp scripts/pre-commit .git/hooks/pre-commit
    chmod +x .git/hooks/pre-commit
    echo "✅ Pre-commit hook installed"
fi

echo "✅ Pre-push hook installed"
echo ""
echo "To test the hook:"
echo "  git push --dry-run origin main"
echo ""
echo "To skip the hook temporarily:"
echo "  git push --no-verify origin main"
```

Make it executable:
```bash
chmod +x scripts/setup-git-hooks.sh
```

## Continuous Integration Alternative

For GitHub Actions CI/CD, create `.github/workflows/quality-checks.yml`:

```yaml
name: Quality Checks

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  quality-checks:
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
        extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql

    - name: Cache Composer packages
      id: composer-cache
      uses: actions/cache@v3
      with:
        path: vendor
        key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
        restore-keys: |
          ${{ runner.os }}-php-

    - name: Install dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader

    - name: Copy environment file
      run: php -r "file_exists('.env') || copy('.env.example', '.env');"

    - name: Generate application key
      run: php artisan key:generate

    - name: Set directory permissions
      run: chmod -R 777 storage bootstrap/cache

    - name: Run PHP syntax check
      run: find app/ -name "*.php" -exec php -l {} \;

    - name: Run Laravel Pint
      run: ./vendor/bin/pint --test

    - name: Run PHPStan (if installed)
      run: |
        if [ -f vendor/bin/phpstan ]; then
          ./vendor/bin/phpstan analyse --memory-limit=1G
        fi
      continue-on-error: true

    - name: Run unit tests
      run: ./vendor/bin/pest --testsuite=unit

    - name: Run feature tests
      run: ./vendor/bin/pest --testsuite=feature
      env:
        DB_CONNECTION: mysql
        DB_HOST: 127.0.0.1
        DB_PORT: 3306
        DB_DATABASE: testing
        DB_USERNAME: root
        DB_PASSWORD: secret
```

## Troubleshooting

### Common Issues

**1. Hook not running:**
```bash
# Check if hook exists and is executable
ls -la .git/hooks/pre-push
```

**2. Permission denied:**
```bash
chmod +x .git/hooks/pre-push
```

**3. Tests failing in hook but passing manually:**
```bash
# Check if environment is consistent
cd /path/to/your/project
./vendor/bin/pest --testsuite=unit
```

**4. Hook taking too long:**
- Use the lightweight version
- Run only critical tests in the hook
- Move comprehensive tests to CI/CD

### Performance Optimization

**Parallel test execution:**
```bash
# In the hook, add parallel processing
./vendor/bin/pest --parallel --testsuite=unit
```

**Incremental checks:**
```bash
# Only check changed files
changed_files=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$')
if [ ! -z "$changed_files" ]; then
    ./vendor/bin/pint --test $changed_files
fi
```

## Best Practices

1. **Keep hooks fast** - Under 60 seconds total
2. **Fail fast** - Stop on first failure
3. **Clear output** - Use colors and emojis for readability
4. **Provide solutions** - Tell developers how to fix issues
5. **Allow bypassing** - For emergencies, allow `--no-verify`
6. **Log execution** - Track hook performance and failures

## Integration with Development Workflow

### IDE Integration
Most IDEs can run these checks automatically:
- **PHPStorm**: Configure File Watchers
- **VS Code**: Use extensions like "PHP Intelephense"

### Team Onboarding
Add to your project README:

```markdown
## Development Setup

1. Clone the repository
2. Run `composer install`
3. Copy `.env.example` to `.env`
4. Run `./scripts/setup-git-hooks.sh`
5. You're ready to develop!
```

This pre-push hook ensures code quality while maintaining developer productivity and preventing broken code from reaching production.