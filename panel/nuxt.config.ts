export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  srcDir: 'app',
  devtools: { enabled: true },
  ssr: false,
  modules: ['@nuxtjs/tailwindcss', '@nuxtjs/i18n', '@vite-pwa/nuxt'],
  css: [
    '@fontsource-variable/noto-sans-arabic/wght.css',
    '~/assets/css/tokens.css',
    'maplibre-gl/dist/maplibre-gl.css',
  ],
  tailwindcss: {
    cssPath: false,
  },
  i18n: {
    defaultLocale: 'en',
    strategy: 'no_prefix',
    detectBrowserLanguage: false,
    langDir: 'locales',
    locales: [
      { code: 'en', language: 'en', name: 'English', dir: 'ltr', file: 'en.json' },
      { code: 'ar', language: 'ar', name: 'العربية', dir: 'rtl', file: 'ar.json' },
    ],
  },
  pwa: {
    registerType: 'autoUpdate',
    includeAssets: ['favicon.ico', 'favicon-32.png', 'apple-touch-icon.png'],
    manifest: {
      id: '/',
      name: 'Smart Inspection',
      short_name: 'Inspection',
      description: 'Inspection cases, employees, schedules, and live operations.',
      start_url: '/map',
      scope: '/',
      display: 'standalone',
      display_override: ['window-controls-overlay', 'standalone'],
      orientation: 'any',
      background_color: '#f7f7fa',
      theme_color: '#4f46e5',
      categories: ['business', 'productivity'],
      icons: [
        { src: '/pwa-192x192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
        { src: '/pwa-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
        { src: '/pwa-maskable-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
      ],
      shortcuts: [
        { name: 'Live map', short_name: 'Map', url: '/map', icons: [{ src: '/pwa-192x192.png', sizes: '192x192' }] },
        { name: 'Cases', short_name: 'Cases', url: '/cases', icons: [{ src: '/pwa-192x192.png', sizes: '192x192' }] },
        { name: 'Employees', short_name: 'Employees', url: '/employees', icons: [{ src: '/pwa-192x192.png', sizes: '192x192' }] },
      ],
    },
    workbox: {
      globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
      additionalManifestEntries: [
        { url: '/', revision: process.env.PWA_REVISION || String(Date.now()) },
      ],
      navigateFallback: '/',
      navigateFallbackDenylist: [/^\/api\//],
      cleanupOutdatedCaches: true,
      clientsClaim: true,
      skipWaiting: true,
    },
    client: {
      installPrompt: true,
      periodicSyncForUpdates: 3600,
    },
    devOptions: {
      enabled: false,
    },
  },
  vite: {
    worker: {
      format: 'es',
    },
  },
  app: {
    head: {
      title: 'Smart Inspection',
      link: [
        { rel: 'icon', type: 'image/png', href: '/favicon-32.png' },
        { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' },
      ],
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover' },
        { name: 'color-scheme', content: 'light' },
        { name: 'theme-color', content: '#4f46e5' },
        { name: 'mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-status-bar-style', content: 'default' },
        { name: 'apple-mobile-web-app-title', content: 'Inspection' },
      ],
      style: [
        {
          innerHTML: `
            #__nuxt:empty {
              position: fixed;
              inset: 0;
              display: grid;
              place-items: center;
              background: #f6f6f9;
              color: #27272f;
            }
            #__nuxt:empty::before {
              content: '';
              width: 42px;
              height: 42px;
              border-radius: 14px;
              background: #5b5ce2;
              box-shadow: 0 12px 28px rgba(79, 70, 229, .24);
              animation: smart-inspection-boot 1s ease-in-out infinite alternate;
            }
            @keyframes smart-inspection-boot {
              from { transform: translateY(2px) scale(.92); opacity: .72; }
              to { transform: translateY(-2px) scale(1); opacity: 1; }
            }
          `,
        },
      ],
    },
  },
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || '',
      apiPort: process.env.NUXT_PUBLIC_API_PORT || '18000',
      reverbAppKey: process.env.NUXT_PUBLIC_REVERB_APP_KEY || '',
      reverbHost: process.env.NUXT_PUBLIC_REVERB_HOST || '',
      reverbPort: process.env.NUXT_PUBLIC_REVERB_PORT || '18080',
      reverbScheme: process.env.NUXT_PUBLIC_REVERB_SCHEME || 'http',
    },
  },
})
