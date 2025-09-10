# AnglerHub

A comprehensive fishing companion app built with Laravel and Vue.js PWA.

## Features

- **Catch Tracking**: Record your catches with photos, location, weather conditions, and detailed information
- **Goal Setting**: Set and track fishing goals (species targets, weight goals, location challenges)
- **Weather Integration**: Automatic weather logging for better catch correlation
- **Statistics & Analytics**: Detailed insights into your fishing performance
- **Progressive Web App**: Works offline and can be installed on mobile devices

## Technology Stack

- **Backend**: Laravel 10+ with Sanctum authentication
- **Frontend**: Vue.js 3 with PWA capabilities
- **Database**: MySQL/PostgreSQL
- **File Storage**: Local/S3-compatible storage
- **Testing**: Pest PHP testing framework

## Installation

1. Clone the repository
2. Copy `.env.example` to `.env` and configure your database
3. Install dependencies: `composer install`
4. Generate application key: `php artisan key:generate`
5. Run migrations: `php artisan migrate`
6. Start the development server: `php artisan serve`

## API Documentation

The API follows RESTful conventions and is secured with Laravel Sanctum.

### Authentication Endpoints
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - Logout current session
- `GET /api/auth/user` - Get current user profile

### Catch Management
- `GET /api/catches` - List user's catches (with filtering)
- `POST /api/catches` - Record new catch
- `GET /api/catches/{id}` - Get specific catch
- `PUT /api/catches/{id}` - Update catch
- `DELETE /api/catches/{id}` - Delete catch

### Goal Management
- `GET /api/goals` - List user's goals
- `POST /api/goals` - Create new goal
- `GET /api/goals/{id}` - Get specific goal
- `PUT /api/goals/{id}` - Update goal
- `POST /api/goals/{id}/complete` - Mark goal as completed

## Development

### Running Tests
```bash
./vendor/bin/pest
```

### Code Style
```bash
./vendor/bin/pint
```

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).