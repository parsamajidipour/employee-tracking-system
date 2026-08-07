// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  srcDir: 'app',
  devtools: { enabled: true },
  // A pure SPA, deliberately — not just for map.vue's MapLibre/Echo (which
  // already needed client-only rendering; see its own ssr:false, now
  // redundant and removed). With SSR on, index.vue's server-side redirect
  // to /map raced the auth middleware: middleware only runs client-side
  // (it can't safely fetch /api/user during SSR without forwarding the
  // browser's session cookie), so the server unconditionally rendered the
  // full, unauthenticated /map shell on every hard refresh, then the client
  // corrected to /login post-hydration — a real, jarring hydration mismatch
  // on every single reload, not just a cosmetic flash. Going full-SPA
  // removes server rendering from the equation entirely: every route
  // resolves client-side, where the session cookie and the auth check are
  // both actually available. Fine for an internal ops console with no SEO
  // need, and consistent with the "boring option" this project favors.
  ssr: false,
  modules: ['@nuxtjs/tailwindcss'],
  css: ['maplibre-gl/dist/maplibre-gl.css'],
  // maplibre-gl bundles its own worker via `new Worker(new URL(...))`; Vite's
  // dep optimizer pre-bundles that worker chunk into a cache path it doesn't
  // actually keep in sync with the main module in dev, which 404s the worker
  // script at runtime — vector tile parsing (needed once the live map has a
  // vector source, not just raster) silently never happens as a result.
  // Excluding it from optimization makes Vite serve maplibre-gl's own worker
  // file directly instead.
  vite: {
    optimizeDeps: {
      exclude: ['maplibre-gl'],
    },
  },
  runtimeConfig: {
    public: {
      // The api/ origin as seen by the browser — must be in api/'s
      // SANCTUM_STATEFUL_DOMAINS and CORS_ALLOWED_ORIGINS.
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000',
      // Reverb, as seen by the browser — see panel/.env.example for what
      // each of these needs to match on the api/ side.
      reverbAppKey: process.env.NUXT_PUBLIC_REVERB_APP_KEY || '',
      reverbHost: process.env.NUXT_PUBLIC_REVERB_HOST || 'localhost',
      reverbPort: process.env.NUXT_PUBLIC_REVERB_PORT || '8080',
      reverbScheme: process.env.NUXT_PUBLIC_REVERB_SCHEME || 'http',
    },
  },
})
