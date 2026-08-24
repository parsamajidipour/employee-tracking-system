<script setup lang="ts">
import { Map as MapLibreMap, setWorkerUrl } from 'maplibre-gl'
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
const hasPositioned = ref(props.lat !== null && props.lng !== null)
let map: MapLibreMap | undefined

function emitCenter() {
  if (!map) return
  const center = map.getCenter()
  emit('update:lat', Number(center.lat.toFixed(6)))
  emit('update:lng', Number(center.lng.toFixed(6)))
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
    zoom: props.lat !== null ? 15 : 11,
    maxBounds: [
      [52.1, 16.6],
      [59.95, 26.6],
    ],
  })

  if (hasPositioned.value) {
    map.on('load', emitCenter)
  }

  // Only a user-driven move (drag/scroll/touch) carries an originalEvent —
  // a programmatic `setCenter`/`jumpTo` does not. That's what lets us tell
  // "the admin actually chose a spot" apart from "the map merely opened".
  map.on('moveend', (e) => {
    if (!e.originalEvent) return
    hasPositioned.value = true
    emitCenter()
  })
})

onUnmounted(() => {
  map?.remove()
})
</script>

<template>
  <div class="relative h-full w-full overflow-hidden rounded-md">
    <div ref="mapContainer" class="h-full w-full bg-surface-sunken"></div>

    <div class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-full">
      <svg width="34" height="44" viewBox="0 0 34 44" fill="none">
        <path
          d="M17 0C7.6 0 0 7.6 0 17c0 12.7 17 27 17 27s17-14.3 17-27C34 7.6 26.4 0 17 0Z"
          :fill="hasPositioned ? '#4f46e5' : '#9a9aa6'"
        />
        <circle cx="17" cy="17" r="6.5" fill="white" />
      </svg>
    </div>
    <div class="pointer-events-none absolute left-1/2 top-1/2 h-1.5 w-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-black/30"></div>

    <div
      class="surface pointer-events-none absolute left-3 top-3 px-3 py-2 text-[12.5px]"
      :class="hasPositioned ? 'text-ink-soft' : 'font-semibold text-state-warning'"
    >
      {{ hasPositioned ? 'Move the map to adjust the pin.' : 'Move the map to place the pin on the property — required.' }}
    </div>
  </div>
</template>
