<script setup lang="ts">
import { GeoJSONSource, LngLatBounds, Map as MapLibreMap, Marker as MapLibreMarker, Popup as MapLibrePopup, addProtocol, setWorkerUrl } from 'maplibre-gl'
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'
import { Protocol as PMTilesProtocol } from 'pmtiles'
import { layers as protomapsLayers, namedFlavor } from '@protomaps/basemaps'
import { shiftColor } from '~/utils/mapMarker'
import { formatDistance } from '~/utils/formatDistance'

interface TrailPoint {
  lng: number
  lat: number
  distance_m: number
  accuracy_m: number | null
  speed_mps: number | null
  heading_deg: number | null
  battery_pct: number | null
  recorded_at: string
  shift_index: number | null
}
interface TrailShift { index: number; start: string; end: string; label: string; distance_m: number }
interface Trail {
  date: string
  start?: string
  end?: string
  distance_m: number
  average_speed_mps: number | null
  max_speed_mps: number | null
  average_accuracy_m: number | null
  first_point_at: string | null
  last_point_at: string | null
  points_count: number
  shifts: TrailShift[]
  points: TrailPoint[]
}

const UNASSIGNED_COLOR = '#8b8b98'

function todayLocalDate(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

const route = useRoute()
const employeeId = Number(route.params.id)

const { data: employeesData, load: loadEmployees } = useEmployees()
const employee = computed(() => employeesData.value?.find((item) => item.id === employeeId) ?? null)

const selectedDate = ref(todayLocalDate())
const selectedShift = ref<number | null>(null)
const trail = ref<Trail | null>(null)
const trailLoading = ref(false)
const mapError = ref<string | null>(null)
const error = ref<string | null>(null)

const mapContainer = ref<HTMLDivElement | null>(null)
let map: MapLibreMap | undefined
let hoverPopup: MapLibrePopup | undefined
let terminalMarkers: MapLibreMarker[] = []

function timeLabel(value: string): string {
  return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

function terminalElement(kind: 'S' | 'E', color: string): HTMLDivElement {
  const el = document.createElement('div')
  el.className = 'flex h-[18px] w-[18px] items-center justify-center rounded-full border-2 border-white text-[11px] font-extrabold shadow-raised'
  el.style.backgroundColor = color
  el.style.color = '#0b0b10'
  el.textContent = kind
  return el
}

function attachMarkerHoverTooltip(marker: MapLibreMarker, html: string) {
  const el = marker.getElement()
  el.addEventListener('mouseenter', () => {
    if (!map || !hoverPopup) return
    hoverPopup.setLngLat(marker.getLngLat()).setHTML(html).addTo(map)
  })
  el.addEventListener('mouseleave', () => hoverPopup?.remove())
}

function distanceMeters(a: TrailPoint, b: TrailPoint): number {
  const R = 6371000
  const dLat = ((b.lat - a.lat) * Math.PI) / 180
  const dLng = ((b.lng - a.lng) * Math.PI) / 180
  const lat1 = (a.lat * Math.PI) / 180
  const lat2 = (b.lat * Math.PI) / 180
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2
  return 2 * R * Math.asin(Math.sqrt(h))
}

/**
 * Walks the raw points, keeping one only once it clears `minMeters` from the
 * last *kept* point (not the last raw point). Comparing against a stable
 * anchor — instead of point-to-point — is what keeps GPS drift that
 * oscillates around a stationary spot from accumulating into a fake path:
 * each drift hop can individually clear the recorder's own 20m threshold
 * (so `distance_m` on it is still nonzero) while never actually carrying the
 * anchor anywhere, so it keeps getting dropped until a point genuinely
 * displaces from where the walk last stood.
 */
function anchorWalk(points: TrailPoint[], minMeters: number): TrailPoint[] {
  if (points.length === 0) return points
  const kept: TrailPoint[] = [points[0]!]
  for (let i = 1; i < points.length; i++) {
    const point = points[i]!
    if (distanceMeters(kept[kept.length - 1]!, point) >= minMeters) kept.push(point)
  }
  const last = points[points.length - 1]!
  if (kept[kept.length - 1] !== last) kept.push(last)
  return kept
}

/**
 * Nearby points collapse into one marker so a dense, mostly-stationary stretch of
 * a day doesn't create thousands of DOM markers and hang the browser. The
 * hover-nearest lookup still uses the full point list — only the rendered
 * dots are thinned.
 */
function decimatePoints(points: TrailPoint[], minMeters = 12, hardCap = 400): TrailPoint[] {
  const kept = anchorWalk(points, minMeters)
  if (kept.length <= hardCap) return kept
  const stride = Math.ceil(kept.length / hardCap)
  return kept.filter((_, i) => i % stride === 0 || i === kept.length - 1)
}

/**
 * A coarser anchor radius than `decimatePoints` uses for dots — the line
 * only needs to show where the employee actually went, not every GPS fix.
 */
function linePath(points: TrailPoint[]): TrailPoint[] {
  return anchorWalk(points, 25)
}

function nearestPoint(points: TrailPoint[], lat: number, lng: number): TrailPoint {
  let closest = points[0]!
  let bestDistance = Infinity
  for (const point of points) {
    const distance = (point.lat - lat) ** 2 + (point.lng - lng) ** 2
    if (distance < bestDistance) {
      bestDistance = distance
      closest = point
    }
  }
  return closest
}

let hoverPoints: TrailPoint[] = []

function attachHoverLayerTooltip(map: MapLibreMap, layerId: string) {
  map.on('mousemove', layerId, (event) => {
    if (!hoverPopup || hoverPoints.length === 0) return
    map.getCanvas().style.cursor = 'pointer'
    const point = nearestPoint(hoverPoints, event.lngLat.lat, event.lngLat.lng)
    hoverPopup.setLngLat(event.lngLat).setHTML(`<div class="text-xs font-medium">${timeLabel(point.recorded_at)}</div>`).addTo(map)
  })
  map.on('mouseleave', layerId, () => {
    map.getCanvas().style.cursor = ''
    hoverPopup?.remove()
  })
}

function clearOverlays() {
  for (const marker of terminalMarkers) marker.remove()
  terminalMarkers = []
}

function visiblePoints(): TrailPoint[] {
  if (selectedShift.value === null) return []
  return (trail.value?.points ?? []).filter((point) => point.shift_index === selectedShift.value)
}

function groupByShift(points: TrailPoint[]): Map<number | null, TrailPoint[]> {
  const groups = new Map<number | null, TrailPoint[]>()
  for (const point of points) {
    const key = point.shift_index
    if (!groups.has(key)) groups.set(key, [])
    groups.get(key)!.push(point)
  }
  return groups
}

function renderTrail() {
  if (!map?.isStyleLoaded()) return
  clearOverlays()

  const points = visiblePoints()
  hoverPoints = points
  if (points.length === 0) {
    ;(map.getSource('history-trail') as GeoJSONSource)?.setData({ type: 'FeatureCollection', features: [] })
    ;(map.getSource('history-dots') as GeoJSONSource)?.setData({ type: 'FeatureCollection', features: [] })
    return
  }

  const groups = groupByShift(points)
  const lineFeatures: object[] = []
  const dotFeatures: object[] = []
  const bounds = new LngLatBounds([points[0]!.lng, points[0]!.lat], [points[0]!.lng, points[0]!.lat])

  for (const [shiftIndex, groupPoints] of groups) {
    if (groupPoints.length === 0) continue
    const color = shiftIndex === null ? UNASSIGNED_COLOR : shiftColor(shiftIndex)

    lineFeatures.push({
      type: 'Feature',
      properties: { color },
      geometry: { type: 'LineString', coordinates: linePath(groupPoints).map((p) => [p.lng, p.lat]) },
    })

    const first = groupPoints[0]!
    const last = groupPoints.at(-1)!

    const start = new MapLibreMarker({ element: terminalElement('S', '#22c55e') }).setLngLat([first.lng, first.lat]).addTo(map)
    attachMarkerHoverTooltip(start, `<div class="text-xs font-medium">Start · ${timeLabel(first.recorded_at)}</div>`)
    terminalMarkers.push(start)

    const end = new MapLibreMarker({ element: terminalElement('E', '#f43f5e') }).setLngLat([last.lng, last.lat]).addTo(map)
    attachMarkerHoverTooltip(end, `<div class="text-xs font-medium">End · ${timeLabel(last.recorded_at)}</div>`)
    terminalMarkers.push(end)

    for (const point of groupPoints) bounds.extend([point.lng, point.lat])
    for (const point of decimatePoints(groupPoints)) {
      dotFeatures.push({ type: 'Feature', properties: { color }, geometry: { type: 'Point', coordinates: [point.lng, point.lat] } })
    }
  }

  ;(map.getSource('history-trail') as GeoJSONSource)?.setData({ type: 'FeatureCollection', features: lineFeatures })
  ;(map.getSource('history-dots') as GeoJSONSource)?.setData({ type: 'FeatureCollection', features: dotFeatures })

  map.fitBounds(bounds, { padding: 72, maxZoom: 14, duration: 500 })
}

async function loadTrail() {
  trailLoading.value = true
  try {
    trail.value = await apiFetch<Trail>(`/api/v1/employees/${employeeId}/trail?date=${encodeURIComponent(selectedDate.value)}`)
    const shifts = trail.value.shifts
    if (selectedShift.value === null || !shifts.some((shift) => shift.index === selectedShift.value)) {
      selectedShift.value = shifts[0]?.index ?? null
    }
    error.value = null
    renderTrail()
  } catch {
    error.value = 'Could not load activity for this day.'
  } finally {
    trailLoading.value = false
  }
}

const selectedDistanceM = computed(() => {
  if (!trail.value || selectedShift.value === null) return 0
  return trail.value.shifts.find((shift) => shift.index === selectedShift.value)?.distance_m ?? 0
})

watch(selectedShift, renderTrail)

onMounted(() => {
  loadEmployees()

  setWorkerUrl(maplibreWorkerUrl)
  addProtocol('pmtiles', new PMTilesProtocol().tile)

  const basemapUrl = `${apiOrigin()}/api/basemap/oman.pmtiles`

  fetch(basemapUrl, { method: 'HEAD' })
    .then((response) => {
      if (!response.ok) mapError.value = 'Map tiles are unavailable on the server (basemap file missing). Contact an administrator.'
    })
    .catch(() => {
      mapError.value = 'Could not reach the map tile server.'
    })

  map = new MapLibreMap({
    container: mapContainer.value!,
    style: {
      version: 8,
      sources: { protomaps: { type: 'vector', url: `pmtiles://${basemapUrl}`, attribution: '© OpenStreetMap contributors', maxzoom: 14 } },
      layers: protomapsLayers('protomaps', namedFlavor('dark'), { lang: 'en' }),
    },
    center: [58.5922, 23.6144],
    zoom: 9,
    maxBounds: [
      [52.1, 16.6],
      [59.95, 26.6],
    ],
  })
  map.on('error', (e) => {
    if (!mapError.value) mapError.value = 'The map could not be loaded.'
    console.error('MapLibre error', e.error)
  })
  map.on('load', () => {
    if (!map) return
    hoverPopup = new MapLibrePopup({ closeButton: false, closeOnClick: false })

    map.addSource('history-trail', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } })
    map.addLayer({
      id: 'history-trail-line',
      type: 'line',
      source: 'history-trail',
      paint: { 'line-color': ['get', 'color'], 'line-width': 3.5, 'line-opacity': 0.9 },
      layout: { 'line-cap': 'round', 'line-join': 'round' },
    })

    map.addSource('history-dots', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } })
    map.addLayer({
      id: 'history-dots-circle',
      type: 'circle',
      source: 'history-dots',
      paint: { 'circle-radius': 3, 'circle-color': ['get', 'color'], 'circle-opacity': 0.85 },
    })

    attachHoverLayerTooltip(map, 'history-trail-line')
    attachHoverLayerTooltip(map, 'history-dots-circle')

    renderTrail()
  })

  loadTrail()
})

watch(selectedDate, loadTrail)

onUnmounted(() => map?.remove())
</script>

<template>
  <AppShell :title="`${employee?.name ?? 'Employee'} histories`" subtitle="Daily routes by shift" :back-to="`/employees/${employeeId}`">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="trailLoading" @click="loadTrail">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="trailLoading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
      <Button variant="secondary" size="sm" :to="`/employees/${employeeId}`">Employee shifts</Button>
    </template>
    <InlineAlert v-if="error" class="mb-4">{{ error }}</InlineAlert>

    <form class="surface-flat mb-4 flex flex-wrap items-end gap-4 p-4" @submit.prevent>
      <div>
        <label for="history-date" class="mb-1.5 block text-[12.5px] font-medium text-ink-soft">Date</label>
        <input id="history-date" v-model="selectedDate" type="date" :max="todayLocalDate()" class="field w-48" />
      </div>

      <div
        v-if="trail && trail.shifts.length > 0"
        class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap"
        role="radiogroup"
        aria-label="Shift"
      >
        <label
          v-for="shift in trail.shifts"
          :key="shift.index"
          class="flex h-11 cursor-pointer items-center gap-2.5 rounded-md border px-3.5 text-[13.5px] transition-colors duration-fast"
          :class="selectedShift === shift.index ? 'border-primary bg-primary-soft text-primary-strong' : 'border-hairline bg-surface text-ink-soft hover:border-primary/60'"
        >
          <input v-model="selectedShift" type="radio" name="shift" class="sr-only" :value="shift.index" />
          <span class="h-2.5 w-2.5 flex-none rounded-full" :style="{ backgroundColor: shiftColor(shift.index) }"></span>
          <span class="font-medium">{{ shift.label }}</span>
          <span class="tabular text-ink-faint">{{ formatDistance(shift.distance_m) }}</span>
        </label>
      </div>

      <span v-if="trailLoading" class="pb-3 text-[12.5px] text-ink-faint">Loading…</span>
    </form>

    <section class="surface-flat relative h-[60vh] min-h-[420px] overflow-hidden">
      <div ref="mapContainer" class="!absolute !inset-0 bg-surface-sunken" />

      <div
        v-if="trail && trail.points.length > 0"
        class="surface-dark absolute left-3 top-3 z-10 flex gap-3 px-3 py-2"
      >
        <div class="flex items-center gap-1.5">
          <Icon name="route" class="h-3.5 w-3.5 text-primary" />
          <span class="tabular text-[12.5px] font-semibold text-ink-dark">{{ formatDistance(selectedDistanceM) }}</span>
        </div>
        <div class="h-full w-px bg-hairline-dark" />
        <div class="flex items-center gap-1.5">
          <Icon name="map-pin" class="h-3.5 w-3.5 text-primary" />
          <span class="tabular text-[12.5px] font-semibold text-ink-dark">{{ trail.points_count }} pts</span>
        </div>
      </div>

      <div v-if="mapError" class="absolute inset-x-4 top-4 z-10">
        <InlineAlert>{{ mapError }}</InlineAlert>
      </div>
      <div
        v-if="!trailLoading && trail && trail.points.length === 0"
        class="surface absolute inset-x-4 top-4 z-10 px-4 py-3"
      >
        <EmptyState icon="route" message="No tracked activity for this day." />
      </div>
    </section>
  </AppShell>
</template>
