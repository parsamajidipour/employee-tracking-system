<script setup lang="ts">
import { Map as MapLibreMap, Marker as MapLibreMarker } from 'maplibre-gl'
import type { StalenessBucket } from '~/composables/usePositions'

// MapLibre and Laravel Echo/pusher-js both touch window/WebSocket at module
// load time — never safe to evaluate during SSR. Rendering this whole page
// client-only sidesteps that outright, rather than chasing it library by
// library with dynamic imports.
definePageMeta({ ssr: false })

interface WindowInfo {
  start: string
  end: string
  source: string
}

const { positions, now, stalenessBucket } = usePositions()

const mapContainer = ref<HTMLDivElement | null>(null)
let map: MapLibreMap | undefined
const markers = new Map<number, MapLibreMarker>()

const selectedEmployeeId = ref<number | null>(null)
const selectedWindow = ref<WindowInfo | null>(null)
const sessionStartedAt = ref<string | null>(null)
const detailLoading = ref(false)
const detailError = ref(false)

const selectedPosition = computed(
  () => positions.value.find((position) => position.employee_id === selectedEmployeeId.value) ?? null,
)

const STALENESS_COLOR: Record<StalenessBucket, string> = {
  fresh: '#16a34a', // under 1 minute
  aging: '#d97706', // 1 to 5 minutes
  stale: '#dc2626', // over 5 minutes
}

function markerElement(): HTMLDivElement {
  const el = document.createElement('div')
  el.className = 'h-4 w-4 rounded-full border-2 border-white shadow-md cursor-pointer'
  return el
}

async function selectEmployee(employeeId: number) {
  selectedEmployeeId.value = employeeId
  detailLoading.value = true
  detailError.value = false
  selectedWindow.value = null
  sessionStartedAt.value = null

  try {
    const today = new Date().toISOString().slice(0, 10)
    const [windowResult, sessionResult] = await Promise.all([
      apiFetch<{ date: string; window: WindowInfo | null }>(`/api/v1/employees/${employeeId}/window?date=${today}`),
      apiFetch<{ started_at: string | null }>(`/api/v1/employees/${employeeId}/session`),
    ])
    selectedWindow.value = windowResult.window
    sessionStartedAt.value = sessionResult.started_at
  } catch {
    detailError.value = true
  } finally {
    detailLoading.value = false
  }
}

function closeDetail() {
  selectedEmployeeId.value = null
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
    const color = STALENESS_COLOR[stalenessBucket(position.recorded_at)]
    let marker = markers.get(position.employee_id)

    if (!marker) {
      const el = markerElement()
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

// Deep: a Position's fields changing (a delta for an id already on the
// map) needs the same marker-sync pass as an id appearing/disappearing.
watch(positions, syncMarkers, { deep: true })
// Staleness colour depends on elapsed time alone — must still recompute
// between deltas, not just when one arrives.
watch(now, syncMarkers)

onMounted(() => {
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
        },
      },
      layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
    },
    center: [58.4, 23.6],
    zoom: 7,
  })
  map.on('load', syncMarkers)
})

onUnmounted(() => {
  map?.remove()
})
</script>

<template>
  <div class="flex h-[85vh]">
    <div ref="mapContainer" class="flex-1"></div>

    <aside class="flex w-80 flex-none flex-col border-l border-slate-200 bg-white">
      <div v-if="selectedEmployeeId !== null" class="border-b border-slate-200 p-4">
        <div class="mb-2 flex items-start justify-between">
          <h2 class="text-sm font-semibold text-slate-900">{{ selectedPosition?.name ?? 'Employee' }}</h2>
          <button @click="closeDetail" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <p class="mb-3 text-xs text-slate-500">{{ selectedPosition?.team_name ?? 'No team' }}</p>

        <div v-if="detailLoading" class="text-xs text-slate-500">Loading details…</div>
        <div v-else class="space-y-2 text-xs text-slate-700">
          <p v-if="detailError" class="text-red-600">Could not load window/session details.</p>

          <div>
            <span class="font-medium text-slate-900">Today's window:</span>
            <span v-if="selectedWindow">
              {{ new Date(selectedWindow.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }} –
              {{ new Date(selectedWindow.end).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
              <span class="text-slate-400">(graced)</span>
            </span>
            <span v-else class="text-slate-400">No window today</span>
          </div>

          <div>
            <span class="font-medium text-slate-900">Session start:</span>
            {{ sessionStartedAt ? new Date(sessionStartedAt).toLocaleTimeString() : '—' }}
          </div>

          <div v-if="selectedPosition" data-testid="detail-last-update">
            <span class="font-medium text-slate-900">Last update:</span>
            {{ new Date(selectedPosition.recorded_at).toLocaleTimeString() }}
          </div>

          <div v-if="selectedPosition">
            <span class="font-medium text-slate-900">Accuracy:</span>
            {{ selectedPosition.accuracy_m !== null ? `${selectedPosition.accuracy_m} m` : '—' }}
          </div>

          <div v-if="selectedPosition">
            <span class="font-medium text-slate-900">Battery:</span>
            {{ selectedPosition.battery_pct !== null ? `${selectedPosition.battery_pct}%` : '—' }}
          </div>
        </div>

        <button
          disabled
          title="Needs a separate permission — not implemented yet"
          class="mt-3 w-full cursor-not-allowed rounded border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-400"
        >
          Trail
        </button>
      </div>

      <div class="flex-1 overflow-y-auto">
        <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase text-slate-500">
          In window ({{ positions.length }})
        </h2>
        <p v-if="positions.length === 0" class="p-4 text-sm text-slate-400">No employees currently in window.</p>
        <ul>
          <li v-for="position in positions" :key="position.employee_id">
            <button
              @click="focusEmployee(position.employee_id)"
              :data-employee-id="position.employee_id"
              class="flex w-full items-center gap-2 border-b border-slate-100 px-4 py-2 text-left text-sm hover:bg-slate-50"
              :class="{ 'bg-slate-100': selectedEmployeeId === position.employee_id }"
            >
              <span
                class="h-2.5 w-2.5 flex-none rounded-full"
                :style="{ backgroundColor: STALENESS_COLOR[stalenessBucket(position.recorded_at)] }"
              ></span>
              <span class="flex-1 truncate text-slate-900">{{ position.name }}</span>
            </button>
          </li>
        </ul>
      </div>
    </aside>
  </div>
</template>
