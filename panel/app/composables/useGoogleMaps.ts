import { Loader } from '@googlemaps/js-api-loader'

let loaderPromise: Promise<typeof google> | null = null

export function useGoogleMaps() {
  const { public: config } = useRuntimeConfig()

  function load(): Promise<typeof google> {
    if (!loaderPromise) {
      loaderPromise = new Loader({
        apiKey: config.googleMapsApiKey as string,
        version: 'weekly',
        libraries: ['marker'],
      }).load()
    }

    return loaderPromise
  }

  return {
    load,
    mapId: config.googleMapsMapId as string,
    apiKeyConfigured: Boolean(config.googleMapsApiKey),
  }
}
