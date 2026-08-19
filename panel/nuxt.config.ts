export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  srcDir: 'app',
  devtools: { enabled: true },
  ssr: false,
  modules: ['@nuxtjs/tailwindcss'],
  css: ['~/assets/css/tokens.css'],
  tailwindcss: {
    cssPath: false,
  },
  app: {
    head: {
      title: 'Smart Inspection',
      link: [
        { rel: 'icon', type: 'image/png', href: '/favicon-32.png' },
        { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' },
      ],
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'color-scheme', content: 'light dark' },
      ],
      script: [
        {
          innerHTML: `try{var m=localStorage.getItem('theme-mode');if(m&&m!=='system')document.documentElement.dataset.theme=m}catch(e){}`,
        },
      ],
    },
  },
  typescript: {
    tsConfig: {
      compilerOptions: {
        types: ['google.maps'],
      },
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
      googleMapsApiKey: process.env.NUXT_PUBLIC_GOOGLE_MAPS_API_KEY || '',
      googleMapsMapId: process.env.NUXT_PUBLIC_GOOGLE_MAPS_MAP_ID || 'DEMO_MAP_ID',
    },
  },
})
