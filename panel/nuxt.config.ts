// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  srcDir: 'app',
  devtools: { enabled: true },
  modules: ['@nuxtjs/tailwindcss'],
  css: ['maplibre-gl/dist/maplibre-gl.css'],
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
