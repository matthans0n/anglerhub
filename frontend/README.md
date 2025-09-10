# AnglerHub Frontend

Vue.js Progressive Web App (PWA) frontend for AnglerHub fishing companion application.

## Features

- 📱 **Mobile-First Design**: Optimized for field use on phones and tablets
- 🔄 **Offline Support**: Log catches even without internet connection  
- 📍 **GPS Integration**: Automatic location capture for catch logging
- 🌦️ **Weather Integration**: Capture weather conditions automatically
- 📊 **Goal Tracking**: Set and monitor fishing goals and progress
- 📷 **Photo Support**: Multiple photos per catch (planned)
- 🎯 **Personal Records**: Track personal bests automatically

## Technology Stack

- **Framework**: Nuxt.js 3 (Vue.js 3)
- **UI**: Nuxt UI (Tailwind CSS + Headless UI)
- **PWA**: Vite PWA plugin with service workers
- **State Management**: Pinia
- **HTTP Client**: Nuxt's built-in $fetch
- **Icons**: Heroicons + Lucide
- **TypeScript**: Full type safety

## Development Setup

### Prerequisites
- Node.js 18+ and npm 9+
- Laravel backend running (see parent directory)

### Installation

1. **Install dependencies**
   ```bash
   cd frontend
   npm install
   ```

2. **Environment configuration**
   ```bash
   cp .env.example .env
   # Edit .env with your API base URL (default: http://localhost/api)
   ```

3. **Start development server**
   ```bash
   npm run dev
   ```

4. **Access the application**
   - Frontend: http://localhost:3000
   - API: http://localhost/api (Laravel backend)

## Available Scripts

```bash
# Development
npm run dev          # Start development server
npm run build        # Build for production
npm run preview      # Preview production build
npm run generate     # Generate static site

# Code Quality
npm run lint         # Run ESLint
npm run lint:fix     # Fix ESLint issues
npm run type-check   # Run TypeScript checks
```

## Project Structure

```
frontend/
├── assets/              # CSS, images, fonts
│   └── css/
│       └── main.css    # Global styles
├── components/          # Vue components
├── composables/         # Vue composables
│   ├── useApi.ts       # API client
│   ├── useAuth.ts      # Authentication
│   ├── useGeolocation.ts # GPS services
│   └── useOfflineStorage.ts # Offline sync
├── layouts/             # Page layouts
│   └── default.vue     # Main app layout
├── pages/               # File-based routing
│   └── index.vue       # Dashboard page
├── plugins/             # Nuxt plugins
├── public/              # Static assets
│   ├── manifest.json   # PWA manifest
│   └── icons/          # App icons
├── server/              # Server-side code
├── nuxt.config.ts       # Nuxt configuration
└── package.json
```

## API Integration

The frontend connects to the Laravel backend API running on Laravel Sail:

- **Base URL**: `http://localhost/api`
- **Authentication**: Laravel Sanctum tokens
- **CORS**: Configured for local development

### Key API Endpoints Used
- `POST /api/auth/login` - User authentication
- `POST /api/auth/register` - User registration  
- `GET /api/catches` - List catches
- `POST /api/catches` - Create new catch
- `GET /api/goals` - List goals
- `POST /api/goals` - Create new goal

## PWA Features

### Service Workers
- Cache API responses for offline access
- Cache static assets for fast loading
- Background sync for offline catch logging

### Offline Storage
- IndexedDB for catch data when offline
- Automatic sync when connection restored
- Visual indicators for offline mode

### Mobile Optimizations
- Touch-friendly interface design
- Viewport optimizations for mobile browsers
- Safe area handling for iOS devices
- Prevention of double-tap zoom
- Bottom navigation for thumb access

## Environment Variables

```bash
# API Configuration
NUXT_PUBLIC_API_BASE=http://localhost/api

# App Configuration  
NUXT_PUBLIC_APP_NAME=AnglerHub
NUXT_PUBLIC_APP_DESCRIPTION=Your fishing companion

# Development
NUXT_PORT=3000
NUXT_HOST=0.0.0.0
```

## Deployment

### Build for Production
```bash
npm run build
```

### Static Generation
```bash
npm run generate
```

The built application can be deployed to any static hosting service or served from the Laravel backend.

## Contributing

1. Follow Vue.js 3 Composition API patterns
2. Use TypeScript for type safety
3. Follow mobile-first responsive design principles
4. Test offline functionality
5. Ensure accessibility standards

## Related Documentation

- [Laravel Backend](../README.md)
- [API Documentation](../README.md#api-documentation)
- [Nuxt.js Documentation](https://nuxt.com/docs)
- [Vue.js Documentation](https://vuejs.org/guide/)