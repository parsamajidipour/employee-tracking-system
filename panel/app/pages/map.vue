<script setup lang="ts">
import { LngLatBounds, Map as MapLibreMap, Marker as MapLibreMarker, addProtocol } from 'maplibre-gl'
import { Protocol as PMTilesProtocol } from 'pmtiles'
import { layers as protomapsLayers, namedFlavor } from '@protomaps/basemaps'
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

// fresh uses the app's one accent colour (blue-600) — the same colour as
// every interactive element — per the design pass's "single accent colour
// used only for interactive elements and the 'fresh' marker state." Aging/
// stale stay semantic amber/red, not accent.
const STALENESS_COLOR: Record<StalenessBucket, string> = {
  fresh: '#2563eb',
  aging: '#d97706',
  stale: '#dc2626',
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
  // Self-hosted PMTiles basemap (see DECISIONS.md's "Live map tiles" entry)
  // — addProtocol is a global registration, harmless to repeat on
  // remount, but only ever needed once per page load.
  addProtocol('pmtiles', new PMTilesProtocol().tile)

  const { public: config } = useRuntimeConfig()
  const basemapUrl = `${config.apiBase}/api/basemap/oman.pmtiles`

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
      // No `glyphs`/`sprite` URL, deliberately: text labels still render —
      // MapLibre falls back to local system fonts when no glyphs endpoint
      // is configured — but POI icons don't (no local fallback exists for
      // sprite images), which is fine for this plain-Tailwind,
      // no-design-pass page. Either way, nothing reaches a third-party
      // font/sprite host, which is the point of self-hosting the tiles.
      layers: protomapsLayers('protomaps', namedFlavor('light'), { lang: 'en' }),
    },
    center: [58.5922, 23.6144], // Muscat
    zoom: 11,
    // Keeps the initial view — and any pan/zoom out from it — inside the
    // self-hosted PMTiles extract's actual coverage (Oman's bbox; see
    // DECISIONS.md's "Live map tiles" entry and README's extract command).
    // Without this, zooming out or panning east/west past the extract's
    // edge shows flat grey canvas with no tiles at all, not a graceful
    // fallback — MapLibre has nothing to render past data it doesn't have.
    maxBounds: [
      [52.1, 16.6],
      [59.95, 26.6],
    ],
  })
  map.on('load', syncMarkers)

  // Fits to the initial snapshot's positions once, on arrival — not on
  // every later live delta, which would yank the admin's view around every
  // time someone moves. Falls back to the Muscat default center/zoom above
  // when the snapshot is empty (nobody currently in window).
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
  <AppShell title="Map" full-bleed>
    <!-- !absolute/!inset-0, not the plain utilities: maplibre-gl.css sets
         `.maplibregl-map { position: relative }` on this same element (the
         class MapLibre itself adds to whatever container we hand it), and
         at equal specificity, whichever stylesheet loads second wins the
         cascade — not reliably ours. Forcing !important is what actually
         guarantees this stays absolutely positioned and fills its parent,
         regardless of CSS load order. -->
    <div ref="mapContainer" class="!absolute !inset-0"></div>

    <!-- Overlays the map rather than sitting beside it — see AppShell's
         full-bleed mode and the design pass's map-page direction. -->
    <aside
      class="absolute right-4 top-4 flex max-h-[calc(100%-2rem)] w-80 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
    >
      <div v-if="selectedEmployeeId !== null" class="flex-none border-b border-slate-200 p-4">
        <div class="mb-2 flex items-start justify-between">
          <h2 class="text-sm font-semibold text-slate-900">{{ selectedPosition?.name ?? 'Employee' }}</h2>
          <button type="button" @click="closeDetail" class="text-slate-400 hover:text-slate-600" aria-label="Close">✕</button>
        </div>

        <p class="mb-3 text-xs text-slate-500">{{ selectedPosition?.team_name ?? 'No team' }}</p>

        <div v-if="detailLoading" class="text-xs text-slate-500">Loading details…</div>
        <div v-else class="space-y-2 text-xs text-slate-700">
          <InlineAlert v-if="detailError">Could not load window/session details.</InlineAlert>

          <div>
            <span class="font-medium text-slate-900">Today's window:</span>
            <span v-if="selectedWindow" class="tabular-nums">
              {{ new Date(selectedWindow.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }} –
              {{ new Date(selectedWindow.end).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
              <span class="text-slate-400">(graced)</span>
            </span>
            <span v-else class="text-slate-400">No window today</span>
          </div>

          <div class="tabular-nums">
            <span class="font-medium text-slate-900">Session start:</span>
            {{ sessionStartedAt ? new Date(sessionStartedAt).toLocaleTimeString() : '—' }}
          </div>

          <div v-if="selectedPosition" data-testid="detail-last-update" class="tabular-nums">
            <span class="font-medium text-slate-900">Last update:</span>
            {{ new Date(selectedPosition.recorded_at).toLocaleTimeString() }}
          </div>

          <div v-if="selectedPosition" class="tabular-nums">
            <span class="font-medium text-slate-900">Accuracy:</span>
            {{ selectedPosition.accuracy_m !== null ? `${selectedPosition.accuracy_m} m` : '—' }}
          </div>

          <div v-if="selectedPosition" class="tabular-nums">
            <span class="font-medium text-slate-900">Battery:</span>
            {{ selectedPosition.battery_pct !== null ? `${selectedPosition.battery_pct}%` : '—' }}
          </div>
        </div>

        <Button variant="secondary" disabled title="Needs a separate permission — not implemented yet" class="mt-3 w-full justify-center">
          Trail
        </Button>
      </div>

      <div class="flex-1 overflow-y-auto">
        <h2 class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-medium uppercase tracking-wide text-slate-500">
          In window ({{ positions.length }})
        </h2>
        <p v-if="positions.length === 0" class="p-4 text-sm text-slate-400">No employees currently in window.</p>
        <ul>
          <li v-for="position in positions" :key="position.employee_id">
            <button
              type="button"
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
  </AppShell>
</template>
