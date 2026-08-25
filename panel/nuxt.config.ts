export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  srcDir: 'app',
  devtools: { enabled: true },
  ssr: false,
  modules: ['@nuxtjs/tailwindcss'],
  css: ['~/assets/css/tokens.css', 'maplibre-gl/dist/maplibre-gl.css'],
  tailwindcss: {
    cssPath: false,
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
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'color-scheme', content: 'light' },
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
            #__nuxt:empty::after {
              content: 'Loading workspace…';
              position: fixed;
              left: 50%;
              top: calc(50% + 58px);
              transform: translateX(-50%);
              font: 600 13px/1.4 ui-sans-serif, system-ui, sans-serif;
              letter-spacing: -.01em;
              color: #777783;
              white-space: nowrap;
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
