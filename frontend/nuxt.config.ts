// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  devtools: { enabled: true },
  
  // CSS Framework
  css: ['~/assets/css/main.css'],
  
  // Modules
  modules: [
    '@nuxt/ui',
    '@vueuse/nuxt',
    '@pinia/nuxt',
    '@nuxtjs/google-fonts',
    '@nuxtjs/eslint-module',
    '@vite-pwa/nuxt'
  ],
  
  // Google Fonts
  googleFonts: {
    families: {
      Inter: [400, 500, 600, 700],
      'Source Sans Pro': [400, 600, 700]
    },
    display: 'swap'
  },
  
  // UI Framework Configuration
  ui: {
    global: true,
    icons: ['heroicons', 'lucide']
  },
  
  // Runtime Configuration
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost/api',
      appName: 'AnglerHub',
      appDescription: 'Your fishing companion for tracking catches and achieving goals'
    }
  },
  
  // App Configuration
  app: {
    head: {
      title: 'AnglerHub - Fishing Companion',
      meta: [
        { name: 'description', content: 'Track your fishing catches, set goals, and enhance your angling experience with AnglerHub PWA.' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' },
        { name: 'apple-mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-status-bar-style', content: 'default' },
        { name: 'theme-color', content: '#059669' }
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
        { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' },
        { rel: 'manifest', href: '/manifest.json' }
      ]
    }
  },
  
  // PWA Configuration (will be added via Vite PWA plugin)
  vite: {
    define: {
      __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'false'
    }
  },
  
  // Development Configuration
  devServer: {
    port: 3000
  },
  
  // TypeScript Configuration
  typescript: {
    strict: false,
    typeCheck: true
  },
  
  // ESLint Configuration
  eslint: {
    lintOnStart: false
  },
  
  // PWA Configuration
  pwa: {
    registerType: 'autoUpdate',
    workbox: {
      navigateFallback: '/',
      globPatterns: ['**/*.{js,css,html,png,svg,ico}'],
      runtimeCaching: [
        {
          urlPattern: /^https:\/\/api\./,
          handler: 'NetworkFirst',
          options: {
            cacheName: 'api-cache',
            expiration: {
              maxEntries: 100,
              maxAgeSeconds: 60 * 60 // 1 hour
            },
            cacheableResponse: {
              statuses: [0, 200]
            }
          }
        }
      ]
    },
    client: {
      installPrompt: true
    },
    manifest: {
      name: 'AnglerHub - Fishing Companion',
      short_name: 'AnglerHub',
      description: 'Track your fishing catches, set goals, and enhance your angling experience',
      start_url: '/',
      display: 'standalone',
      background_color: '#f8fafc',
      theme_color: '#059669',
      icons: [
        {
          src: '/icons/icon-192x192.png',
          sizes: '192x192',
          type: 'image/png'
        },
        {
          src: '/icons/icon-512x512.png',
          sizes: '512x512', 
          type: 'image/png'
        }
      ]
    }
  },
  
  // Compatibility
  compatibilityDate: '2024-01-15'
})