<script setup lang="ts">
import { Marker as MapLibreMarker, Map as MapLibreMap, setWorkerUrl } from 'maplibre-gl'
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'

const props = defineProps<{
  lat: number | null
  lng: number | null
}>()

const emit = defineEmits<{
  (e: 'update:lat', value: number): void
  (e: 'update:lng', value: number): void
}>()

const DEFAULT_CENTER: [number, number] = [58.5922, 23.6144]

const mapContainer = ref<HTMLDivElement | null>(null)
let map: MapLibreMap | undefined
let marker: MapLibreMarker | undefined

function placeMarker(lngLat: { lng: number; lat: number }) {
  if (!map) return

  if (!marker) {
    marker = new MapLibreMarker({ draggable: true, color: '#4f46e5' }).setLngLat(lngLat).addTo(map)
    marker.on('dragend', () => {
      const pos = marker!.getLngLat()
      emit('update:lat', Number(pos.lat.toFixed(6)))
      emit('update:lng', Number(pos.lng.toFixed(6)))
    })
  } else {
    marker.setLngLat(lngLat)
  }

  emit('update:lat', Number(lngLat.lat.toFixed(6)))
  emit('update:lng', Number(lngLat.lng.toFixed(6)))
}

onMounted(() => {
  setWorkerUrl(maplibreWorkerUrl)

  const initialCenter: [number, number] =
    props.lat !== null && props.lng !== null ? [props.lng, props.lat] : DEFAULT_CENTER

  map = new MapLibreMap({
    container: mapContainer.value!,
    style: {
      version: 8,
      sources: {
        osm: {
          type: 'raster',
          tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
          tileSize: 256,
          attribution: '© OpenStreetMap contributors',
          maxzoom: 19,
        },
      },
      layers: [{ id: 'osm-tiles', type: 'raster', source: 'osm' }],
    },
    center: initialCenter,
    zoom: props.lat !== null ? 15 : 10,
    maxBounds: [
      [52.1, 16.6],
      [59.95, 26.6],
    ],
  })

  map.on('load', () => {
    if (props.lat !== null && props.lng !== null) {
      placeMarker({ lat: props.lat, lng: props.lng })
    }
  })

  map.on('click', (e) => placeMarker(e.lngLat))
})

onUnmounted(() => {
  marker?.remove()
  map?.remove()
})
</script>

<template>
  <div class="relative h-full w-full overflow-hidden rounded-md">
    <div ref="mapContainer" class="h-full w-full bg-surface-sunken"></div>
    <div class="surface pointer-events-none absolute left-3 top-3 px-3 py-2 text-[12.5px] text-ink-soft">
      Click the map to place the property location — drag the pin to adjust.
    </div>
  </div>
</template>
