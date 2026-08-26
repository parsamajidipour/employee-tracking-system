<script setup lang="ts">
import { LngLatBounds, Map as MapLibreMap, Marker, Popup, setWorkerUrl } from 'maplibre-gl'
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'
import type { NearestSurveyor } from '~/composables/useCases'
import { formatDistance } from '~/utils/formatDistance'

const props = defineProps<{
  caseLat: number
  caseLng: number
  candidates: NearestSurveyor[]
  selectedId: number | null
}>()

const emit = defineEmits<{
  (e: 'select', employeeId: number): void
  (e: 'assign', employeeId: number): void
}>()

const mapContainer = ref<HTMLDivElement | null>(null)
let map: MapLibreMap | undefined
let markers: Marker[] = []

function markerButton(color: string, label: string, selected = false): HTMLButtonElement {
  const button = document.createElement('button')
  button.type = 'button'
  button.title = label
  button.setAttribute('aria-label', label)
  Object.assign(button.style, {
    width: selected ? '32px' : '27px',
    height: selected ? '32px' : '27px',
    borderRadius: '999px',
    border: selected ? '4px solid white' : '3px solid white',
    background: color,
    boxShadow: selected ? '0 0 0 3px rgba(79,70,229,.38), 0 5px 16px rgba(20,20,30,.28)' : '0 4px 12px rgba(20,20,30,.24)',
    cursor: 'pointer',
    transition: 'none',
  })
  return button
}

function clearMarkers(): void {
  markers.forEach(marker => marker.remove())
  markers = []
}

function renderMarkers(): void {
  if (!map?.loaded()) return
  clearMarkers()

  const bounds = new LngLatBounds([props.caseLng, props.caseLat], [props.caseLng, props.caseLat])
  const caseElement = markerButton('#111827', 'Property location', true)
  caseElement.style.borderRadius = '9px 9px 9px 2px'
  caseElement.style.transform = 'rotate(-45deg)'
  const caseMarker = new Marker({ element: caseElement, anchor: 'bottom' })
    .setLngLat([props.caseLng, props.caseLat])
    .setPopup(new Popup({ offset: 18 }).setHTML('<strong>Case location</strong><br><span style="font-size:12px;color:#71717a">Property to inspect</span>'))
    .addTo(map)
  markers.push(caseMarker)

  props.candidates.forEach((candidate) => {
    const selected = candidate.employee_id === props.selectedId
    const element = markerButton(
      selected ? '#4f46e5' : candidate.connection_status === 'online' ? '#16a34a' : '#9ca3af',
      `${candidate.name}, ${formatDistance(candidate.distance_m)} away`,
      selected,
    )

    const popupContent = document.createElement('div')
    popupContent.style.minWidth = '176px'
    const name = document.createElement('strong')
    name.textContent = candidate.name
    const detail = document.createElement('p')
    detail.textContent = `${formatDistance(candidate.distance_m)} away · ${candidate.open_case_count} open case${candidate.open_case_count === 1 ? '' : 's'}`
    Object.assign(detail.style, { margin: '4px 0 10px', fontSize: '12px', color: '#71717a' })
    const assign = document.createElement('button')
    assign.type = 'button'
    assign.textContent = `Assign to ${candidate.name.split(' ')[0]}`
    Object.assign(assign.style, {
      width: '100%',
      minHeight: '36px',
      border: '0',
      borderRadius: '8px',
      background: '#4f46e5',
      color: 'white',
      fontSize: '12px',
      fontWeight: '700',
      cursor: 'pointer',
    })
    assign.addEventListener('click', () => emit('assign', candidate.employee_id))
    popupContent.append(name, detail, assign)

    element.addEventListener('click', () => emit('select', candidate.employee_id))
    const marker = new Marker({ element })
      .setLngLat([candidate.lng, candidate.lat])
      .setPopup(new Popup({ offset: 18, closeButton: true }).setDOMContent(popupContent))
      .addTo(map!)
    markers.push(marker)
    bounds.extend([candidate.lng, candidate.lat])
  })

  map.fitBounds(bounds, { padding: 46, maxZoom: 14.5, duration: 0 })
}

onMounted(() => {
  setWorkerUrl(maplibreWorkerUrl)
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
    center: [props.caseLng, props.caseLat],
    zoom: 12,
    maxBounds: [[52.1, 16.6], [59.95, 26.6]],
  })
  map.on('load', renderMarkers)
})

watch(
  () => [props.caseLat, props.caseLng, props.candidates, props.selectedId],
  () => renderMarkers(),
  { deep: true },
)

onUnmounted(() => {
  clearMarkers()
  map?.remove()
})
</script>

<template>
  <div class="relative h-full w-full overflow-hidden bg-surface-sunken">
    <div ref="mapContainer" class="h-full w-full" />
    <div class="surface pointer-events-none absolute left-2.5 top-2.5 flex items-center gap-3 px-2.5 py-2 text-[10.5px] text-ink-soft">
      <span class="flex items-center gap-1"><i class="h-2 w-2 rounded-full bg-state-success" />Online</span>
      <span class="flex items-center gap-1"><i class="h-2 w-2 rounded-full bg-state-neutral" />Last known</span>
    </div>
  </div>
</template>

<style scoped>
:deep(.maplibregl-marker) {
  transition: none !important;
}
</style>
