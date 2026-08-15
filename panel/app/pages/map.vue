<script setup lang="ts">
import { GeoJSONSource, LngLatBounds, Map as MapLibreMap, Marker as MapLibreMarker, addProtocol, setWorkerUrl } from 'maplibre-gl'
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'
import { Protocol as PMTilesProtocol } from 'pmtiles'
import { layers as protomapsLayers, namedFlavor } from '@protomaps/basemaps'
import type { StalenessBucket } from '~/composables/usePositions'

const { positions, now, stalenessBucket } = usePositions()

const mapContainer = ref<HTMLDivElement | null>(null)
let map: MapLibreMap | undefined
const markers = new Map<number, MapLibreMarker>()

const selectedEmployeeId = ref<number | null>(null)
const selectedDistanceM = ref<number | null>(null)
const showTrail = ref(false)
const trailPoints = ref<Array<{ lng: number; lat: number; recorded_at: string }>>([])
const detailLoading = ref(false)
const detailError = ref(false)

const selectedPosition = computed(
  () => positions.value.find((position) => position.employee_id === selectedEmployeeId.value) ?? null,
)

const OFFLINE_COLOR = '#d97706'

function employeeColor(employeeId: number): string {
  return `hsl(${(employeeId * 137.508) % 360} 68% 45%)`
}

function markerColor(employeeId: number, recordedAt: string): string {
  return stalenessBucket(recordedAt) === 'offline' ? OFFLINE_COLOR : employeeColor(employeeId)
}

function markerElement(name: string): HTMLDivElement {
  const el = document.createElement('div')
  el.className = 'group relative h-4 w-4 cursor-pointer rounded-full border-2 border-white shadow-md'
  const label = document.createElement('span')
  label.textContent = name
  label.className = 'pointer-events-none absolute bottom-full left-1/2 mb-2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-surface px-2 py-1 text-xs font-semibold text-ink opacity-0 shadow-raised transition-opacity group-hover:opacity-100'
  el.appendChild(label)
  return el
}

function renderTrail() {
  if (!map?.getSource('employee-trail')) return
  const coordinates = showTrail.value ? trailPoints.value.map((point) => [point.lng, point.lat]) : []
  ;(map.getSource('employee-trail') as GeoJSONSource).setData({
    type: 'Feature',
    properties: {},
    geometry: { type: 'LineString', coordinates },
  })
  if (selectedEmployeeId.value !== null) {
    map.setPaintProperty('employee-trail-line', 'line-color', employeeColor(selectedEmployeeId.value))
  }
}

async function loadTrail() {
  if (!showTrail.value || selectedEmployeeId.value === null) {
    trailPoints.value = []
    renderTrail()
    return
  }

  const result = await apiFetch<{ distance_m: number; points: Array<{ lng: number; lat: number; recorded_at: string }> }>(
    `/api/v1/employees/${selectedEmployeeId.value}/trail`,
  )
  selectedDistanceM.value = result.distance_m
  trailPoints.value = result.points
  renderTrail()
}

async function toggleTrail() {
  showTrail.value = !showTrail.value
  try {
    await loadTrail()
  } catch {
    showTrail.value = false
    detailError.value = true
  }
}

async function selectEmployee(employeeId: number) {
  selectedEmployeeId.value = employeeId
  detailLoading.value = true
  detailError.value = false
  selectedDistanceM.value = null
  showTrail.value = false
  trailPoints.value = []
  renderTrail()

  try {
    const result = await apiFetch<{ distance_m: number }>(`/api/v1/employees/${employeeId}/distance?days=1`)
    selectedDistanceM.value = result.distance_m
  } catch {
    detailError.value = true
  } finally {
    detailLoading.value = false
  }
}

function closeDetail() {
  selectedEmployeeId.value = null
  showTrail.value = false
  trailPoints.value = []
  renderTrail()
}

function focusEmployee(employeeId: number) {
  const position = positions.value.find((p) => p.employee_id === employeeId)
  if (position) map?.flyTo({ center: [position.lng, position.lat], zoom: 14 })
  selectEmployee(employeeId)
}

function syncMarkers() {
  if (!map) return

  const seen = new Set<number>()

  for (const position of positions.value) {
    seen.add(position.employee_id)
    const color = markerColor(position.employee_id, position.recorded_at)
    let marker = markers.get(position.employee_id)

    if (!marker) {
      const el = markerElement(position.name)
      el.dataset.employeeId = String(position.employee_id)
      el.addEventListener('click', () => selectEmployee(position.employee_id))
      marker = new MapLibreMarker({ element: el }).setLngLat([position.lng, position.lat]).addTo(map)
      markers.set(position.employee_id, marker)
    } else {
      marker.setLngLat([position.lng, position.lat])
    }

    markers.get(position.employee_id)!.getElement().style.backgroundColor = color
  }

  for (const [employeeId, marker] of markers) {
    if (!seen.has(employeeId)) {
      marker.remove()
      markers.delete(employeeId)
      if (selectedEmployeeId.value === employeeId) closeDetail()
    }
  }
}

watch(positions, syncMarkers, { deep: true })

watch(positions, (snapshot) => {
  if (!showTrail.value || selectedEmployeeId.value === null) return
  const position = snapshot.find((item) => item.employee_id === selectedEmployeeId.value)
  if (!position) return
  const last = trailPoints.value.at(-1)
  if (last && new Date(last.recorded_at).getTime() >= new Date(position.recorded_at).getTime()) return
  trailPoints.value.push({ lng: position.lng, lat: position.lat, recorded_at: position.recorded_at })
  renderTrail()
}, { deep: true })

watch(now, syncMarkers)

function calmFlavor() {
  return {
    ...namedFlavor('light'),
    background: '#eff5f8',
    earth: '#eaeef1',
    water: '#cadfea',
    park_a: '#dcebe0',
    park_b: '#d4e6da',
    wood_a: '#dcebe0',
    wood_b: '#d4e6da',
    scrub_a: '#e2ebe3',
    scrub_b: '#dbe6dd',
    sand: '#efeade',
    beach: '#efeade',
    buildings: '#dde4e9',
    city_label: '#13333f',
    state_label: '#6e8c99',
    country_label: '#6e8c99',
    ocean_label: '#7fa5b5',
    subplace_label: '#6e8c99',
  }
}

onMounted(() => {
  setWorkerUrl(maplibreWorkerUrl)
  addProtocol('pmtiles', new PMTilesProtocol().tile)

  const { public: config } = useRuntimeConfig()
  const basemapUrl = `${apiOrigin()}/api/basemap/oman.pmtiles`

  map = new MapLibreMap({
    container: mapContainer.value!,
    style: {
      version: 8,
      sources: {
        protomaps: {
          type: 'vector',
          url: `pmtiles://${basemapUrl}`,
          attribution: '© OpenStreetMap contributors',
        },
      },

      layers: protomapsLayers('protomaps', calmFlavor(), { lang: 'en' }),
    },
    center: [58.5922, 23.6144],
    zoom: 11,

    maxBounds: [
      [52.1, 16.6],
      [59.95, 26.6],
    ],
  })
  map.on('load', () => {
    map?.addSource('employee-trail', {
      type: 'geojson',
      data: { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: [] } },
    })
    map?.addLayer({
      id: 'employee-trail-line',
      type: 'line',
      source: 'employee-trail',
      paint: { 'line-color': '#2f9ec0', 'line-width': 4, 'line-opacity': 0.9 },
      layout: { 'line-cap': 'round', 'line-join': 'round' },
    })
    syncMarkers()
  })

  watch(
    positions,
    (snapshot) => {
      const [first, ...rest] = snapshot
      if (!first) return
      const bounds = rest.reduce(
        (b, position) => b.extend([position.lng, position.lat]),
        new LngLatBounds([first.lng, first.lat], [first.lng, first.lat]),
      )
      map?.fitBounds(bounds, { padding: 60, maxZoom: 15, duration: 0 })
    },
    { once: true },
  )
})

onUnmounted(() => {
  map?.remove()
})
</script>

<template>
  <AppShell title="Live map" full-bleed>

    <div ref="mapContainer" class="!absolute !inset-0"></div>

    <aside class="floating absolute right-4 top-4 flex max-h-[calc(100%-2rem)] w-[min(320px,calc(100%-2rem))] flex-col overflow-hidden">
      <div v-if="selectedEmployeeId !== null" class="flex-none border-b border-hairline p-4">
        <div class="flex items-start justify-between gap-2">
          <h2 class="truncate font-semibold">{{ selectedPosition?.name ?? 'Employee' }}</h2>
          <button
            type="button"
            class="-mr-1 -mt-1 rounded-small p-1 text-ink-faint transition-colors hover:text-ink"
            aria-label="Close"
            @click="closeDetail"
          >
            &#10005;
          </button>
        </div>

        <p v-if="detailLoading" class="muted mt-3 text-sm">Loading details…</p>

        <div v-else class="mt-3">
          <InlineAlert v-if="detailError">Could not load distance details.</InlineAlert>

          <dl class="space-y-2 text-sm">
            <div class="flex items-baseline justify-between gap-3">
              <dt class="muted">Status</dt>
              <dd :class="selectedPosition && stalenessBucket(selectedPosition.recorded_at) === 'online' ? 'text-success' : 'text-state-warning'">
                {{ selectedPosition ? stalenessBucket(selectedPosition.recorded_at) : 'offline' }}
              </dd>
            </div>
            <div class="flex items-baseline justify-between gap-3">
              <dt class="muted">Distance today</dt>
              <dd class="tabular font-semibold">{{ selectedDistanceM === null ? '—' : `${(selectedDistanceM / 1000).toFixed(2)} km` }}</dd>
            </div>
            <div v-if="selectedPosition" data-testid="detail-last-update" class="flex items-baseline justify-between gap-3">
              <dt class="muted">Last update</dt>
              <dd class="tabular">{{ new Date(selectedPosition.recorded_at).toLocaleTimeString() }}</dd>
            </div>
          </dl>
          <button
            type="button"
            class="mt-4 flex w-full items-center gap-3 rounded-control border border-hairline bg-surface-muted p-3 text-left text-sm font-medium transition-colors hover:border-primary"
            @click="toggleTrail"
          >
            <span
              class="grid h-6 w-6 place-items-center rounded-small border transition-colors"
              :style="showTrail ? { backgroundColor: employeeColor(selectedEmployeeId!), borderColor: employeeColor(selectedEmployeeId!), color: '#fff' } : {}"
            >{{ showTrail ? '✓' : '' }}</span>
            <span>Show current shift trail</span>
          </button>
        </div>
      </div>

      <div class="flex min-h-0 flex-1 flex-col">
        <h2 class="flex-none px-4 pb-2 pt-4 text-[11px] font-semibold uppercase tracking-[0.8px] text-ink-soft">
          In window ({{ positions.length }})
        </h2>
        <p v-if="positions.length === 0" class="px-4 pb-4 text-sm text-ink-faint">
          No employees currently in window.
        </p>
        <ul v-else class="min-h-0 flex-1 overflow-y-auto pb-2">
          <li v-for="position in positions" :key="position.employee_id">
            <button
              type="button"
              :data-employee-id="position.employee_id"
              class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm transition-colors hover:bg-surface-muted"
              :class="{ 'bg-surface-muted font-medium': selectedEmployeeId === position.employee_id }"
              @click="focusEmployee(position.employee_id)"
            >
              <span
                class="h-2.5 w-2.5 flex-none rounded-full"
                :style="{ backgroundColor: markerColor(position.employee_id, position.recorded_at) }"
              ></span>
              <span class="flex-1 truncate">{{ position.name }}</span>
            </button>
          </li>
        </ul>
      </div>
    </aside>
  </AppShell>
</template>
