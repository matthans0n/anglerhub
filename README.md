# AnglerHub

A comprehensive fishing companion app built with Laravel backend and Vue.js PWA frontend, focusing on solo angler experience and MVP functionality.

## Project Status

✅ **Backend Foundation Complete** (v0.1.0)
- Laravel 10+ API with full CRUD operations
- Authentication with Sanctum
- Database schema with migrations
- Docker development environment
- Testing infrastructure with Pest
- Deployment runbooks and monitoring

🚧 **Next Phase: Vue.js PWA Frontend**
- Progressive Web App with offline capabilities
- Mobile-first responsive design
- API integration with Laravel backend

## Features

### MVP (Minimum Viable Product) - Solo Angler Focus
- **Catch Tracking**: Record catches with species, weight, length, location, and notes
- **Goal Setting**: Set and track personal fishing goals (species, weight, count, location)
- **Location Logging**: GPS coordinates and water body information
- **Weather Integration**: Automatic weather condition logging
- **Photo Management**: Multiple photos per catch
- **Personal Records**: Track personal bests automatically
- **Statistics**: Basic analytics and progress tracking

### Technology Stack

- **Backend**: Laravel 10+ with Sanctum authentication ✅
- **Frontend**: Vue.js 3 with PWA capabilities (planned)
- **Database**: MySQL with comprehensive fishing data schema ✅
- **Development**: Docker with Laravel Sail ✅
- **Testing**: Pest PHP testing framework ✅
- **Deployment**: Production-ready runbooks ✅

## Quick Start

### Prerequisites
- Docker and Docker Compose
- Git

### Development Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/matthans0n/anglerhub.git
   cd anglerhub
   ```

2. **Environment configuration**
   ```bash
   cp .env.example .env
   # Edit .env with your preferred database credentials
   ```

3. **Start with Laravel Sail**
   ```bash
   ./vendor/bin/sail up -d
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate
   ```

4. **Access the application**
   - API: http://localhost
   - Database: localhost:3306 (from host machine)

### Alternative: Local Installation

1. **Install PHP dependencies**
   ```bash
   composer install
   ```

2. **Database setup**
   ```bash
   php artisan key:generate
   php artisan migrate
   ```

3. **Start development server**
   ```bash
   php artisan serve
   ```

## API Documentation

The API follows RESTful conventions and is secured with Laravel Sanctum. All protected endpoints require an `Authorization: Bearer {token}` header.

### Base URL
- Development: `http://localhost/api`
- Production: `https://your-domain.com/api`

### Authentication Endpoints

#### Registration & Login
- `POST /api/auth/register` - Register new user
  ```json
  {
    "name": "John Doe",
    "email": "john@example.com", 
    "password": "password",
    "password_confirmation": "password"
  }
  ```

- `POST /api/auth/login` - User login
  ```json
  {
    "email": "john@example.com",
    "password": "password"
  }
  ```

#### Protected Auth Routes
- `POST /api/auth/logout` - Logout current session
- `POST /api/auth/logout-all` - Logout all sessions
- `GET /api/auth/user` - Get current user profile
- `PUT /api/auth/profile` - Update user profile
- `PUT /api/auth/preferences` - Update user preferences
- `PUT /api/auth/password` - Change password
- `DELETE /api/auth/account` - Delete user account

### Catch Management

- `GET /api/catches` - List user's catches with filtering
  - Query params: `species`, `location`, `start_date`, `end_date`, `limit`, `page`
- `POST /api/catches` - Record new catch
  ```json
  {
    "species": "Largemouth Bass",
    "weight": 2.5,
    "length": 45.0,
    "location": "Lake Example",
    "latitude": 40.7128,
    "longitude": -74.0060,
    "water_body": "Lake",
    "caught_at": "2024-01-15T14:30:00Z",
    "bait_lure": "Spinnerbait",
    "technique": "Cast and retrieve",
    "water_temp": 18.5,
    "air_temp": 22.0,
    "weather_conditions": "Partly cloudy",
    "notes": "Great fight!",
    "is_released": true,
    "photos": ["photo1.jpg", "photo2.jpg"]
  }
  ```
- `GET /api/catches/{id}` - Get specific catch
- `PUT /api/catches/{id}` - Update catch
- `DELETE /api/catches/{id}` - Delete catch
- `GET /api/catches/statistics` - Get catch statistics
- `GET /api/catches/nearby` - Get catches near current location

### Goal Management

- `GET /api/goals` - List user's goals
- `POST /api/goals` - Create new goal
  ```json
  {
    "title": "Catch 10 Bass This Year",
    "description": "Goal to improve bass fishing skills",
    "type": "species",
    "criteria": {
      "species": "bass",
      "timeframe": "yearly"
    },
    "target_value": 10,
    "start_date": "2024-01-01",
    "target_date": "2024-12-31"
  }
  ```
- `GET /api/goals/{id}` - Get specific goal
- `PUT /api/goals/{id}` - Update goal
- `DELETE /api/goals/{id}` - Delete goal
- `POST /api/goals/{id}/complete` - Mark goal as completed
- `POST /api/goals/{id}/pause` - Pause goal progress
- `POST /api/goals/{id}/resume` - Resume paused goal
- `GET /api/goals/statistics` - Get goal statistics
- `POST /api/goals/refresh-progress` - Refresh progress for all goals

### Utilities
- `GET /api/health` - Health check for authenticated requests

## Database Schema

### Catches Table
The catches table stores all fishing catch records with comprehensive data:
- Fish details: species, weight, length
- Location data: GPS coordinates, water body type
- Environmental conditions: water/air temperature, weather
- Catch metadata: bait/lure used, technique, photos
- Personal tracking: release status, personal best flags

### Goals Table
The goals table enables flexible goal setting and progress tracking:
- Goal types: species, weight, count, location, custom
- Flexible criteria storage (JSON)
- Progress tracking with automated calculations
- Timeline management with start/target dates
- Status management: active, completed, paused, cancelled

## Development

### Running Tests
```bash
# Using Sail
./vendor/bin/sail test

# Or locally
./vendor/bin/pest
```

### Code Style
```bash
# Using Sail
./vendor/bin/sail pint

# Or locally
./vendor/bin/pint
```

### Database Operations
```bash
# Fresh migration with seeding
./vendor/bin/sail artisan migrate:fresh --seed

# Create new migration
./vendor/bin/sail artisan make:migration create_example_table

# Rollback migrations
./vendor/bin/sail artisan migrate:rollback
```

## Deployment

The project includes comprehensive deployment runbooks located in `docs/runbooks/`:
- Production deployment steps
- Environment configuration
- Database migration procedures
- Monitoring and alerting setup
- Pre-push hooks for code quality

## Contributing

### Development Workflow
1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes with appropriate tests
4. Run code quality checks: `./vendor/bin/pint && ./vendor/bin/pest`
5. Commit using conventional commits: `git commit -m "feat: add amazing feature"`
6. Push to your fork and submit a Pull Request

### Code Quality Standards
- All code must pass PSR-12 style checks (enforced by Pint)
- All features must include appropriate test coverage
- API endpoints must follow RESTful conventions
- Database changes require migrations
- Commit messages should follow conventional commit format

## Roadmap

### Phase 1: Backend Foundation ✅ COMPLETE
- Laravel API with authentication
- Database schema and migrations
- Docker development environment
- Testing infrastructure
- Deployment preparation

### Phase 2: Vue.js PWA Frontend (In Planning)
- Progressive Web App with offline support
- Mobile-first responsive design
- Camera integration for catch photos
- GPS location services
- Real-time weather integration
- Data synchronization with backend API

### Phase 3: Enhanced Features (Future)
- Social features (catch sharing, leaderboards)
- Advanced analytics and insights
- Weather prediction integration
- Tackle box inventory management
- Fishing calendar and planning

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).