<script setup lang="ts">
import { shiftColor } from '~/utils/mapMarker'
import { LIGHT_MAP_STYLE } from '~/utils/lightMapStyle'
import { formatDistance, formatSpeed } from '~/utils/formatDistance'

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

const { load: loadGoogleMaps, apiKeyConfigured } = useGoogleMaps()

const selectedDate = ref(todayLocalDate())
const selectedShift = ref<number | null>(null)
const trail = ref<Trail | null>(null)
const trailLoading = ref(false)
const mapError = ref<string | null>(null)
const error = ref<string | null>(null)

const mapContainer = ref<HTMLDivElement | null>(null)
let map: google.maps.Map | undefined
let mapsApi: typeof google | undefined
let hoverInfoWindow: google.maps.InfoWindow | undefined
let polylines: google.maps.Polyline[] = []
let terminalMarkers: google.maps.Marker[] = []
let pointMarkers: google.maps.Marker[] = []

function timeLabel(value: string): string {
  return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

function attachHoverTooltip(marker: google.maps.Marker, html: string) {
  marker.addListener('mouseover', () => {
    if (!map || !hoverInfoWindow) return
    hoverInfoWindow.setContent(html)
    hoverInfoWindow.open({ map, anchor: marker })
  })
  marker.addListener('mouseout', () => hoverInfoWindow?.close())
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
 * Nearby points collapse into one marker so a dense, mostly-stationary stretch of
 * a day doesn't create thousands of DOM markers and hang the browser. The
 * polyline path and the hover-nearest lookup still use the full point list —
 * only the rendered dots are thinned.
 */
function decimatePoints(points: TrailPoint[], minMeters = 12, hardCap = 400): TrailPoint[] {
  if (points.length === 0) return points
  const kept: TrailPoint[] = [points[0]!]
  for (let i = 1; i < points.length; i++) {
    const point = points[i]!
    if (distanceMeters(kept[kept.length - 1]!, point) >= minMeters) kept.push(point)
  }
  const last = points[points.length - 1]!
  if (kept[kept.length - 1] !== last) kept.push(last)

  if (kept.length <= hardCap) return kept
  const stride = Math.ceil(kept.length / hardCap)
  return kept.filter((_, i) => i % stride === 0 || i === kept.length - 1)
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

function attachLineHoverTooltip(line: google.maps.Polyline, points: TrailPoint[]) {
  line.addListener('mousemove', (event: google.maps.PolyMouseEvent) => {
    if (!map || !hoverInfoWindow || !event.latLng) return
    const point = nearestPoint(points, event.latLng.lat(), event.latLng.lng())
    hoverInfoWindow.setContent(`<div class="text-xs font-medium">${timeLabel(point.recorded_at)}</div>`)
    hoverInfoWindow.setPosition(event.latLng)
    hoverInfoWindow.open({ map })
  })
  line.addListener('mouseout', () => hoverInfoWindow?.close())
}

function clearOverlays() {
  for (const line of polylines) line.setMap(null)
  for (const marker of terminalMarkers) marker.setMap(null)
  for (const marker of pointMarkers) marker.setMap(null)
  polylines = []
  terminalMarkers = []
  pointMarkers = []
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
  if (!map || !mapsApi) return
  clearOverlays()

  const points = visiblePoints()
  if (points.length === 0) return

  const bounds = new mapsApi.maps.LatLngBounds()
  const groups = groupByShift(points)

  for (const [shiftIndex, groupPoints] of groups) {
    if (groupPoints.length === 0) continue
    const color = shiftIndex === null ? UNASSIGNED_COLOR : shiftColor(shiftIndex)

    const line = new mapsApi.maps.Polyline({
      path: groupPoints.map((p) => ({ lat: p.lat, lng: p.lng })),
      strokeColor: color,
      strokeOpacity: 0.9,
      strokeWeight: 3.5,
      map,
    })
    attachLineHoverTooltip(line, groupPoints)
    polylines.push(line)

    const first = groupPoints[0]!
    const last = groupPoints.at(-1)!

    const start = new mapsApi.maps.Marker({
      position: { lat: first.lat, lng: first.lng },
      map,
      icon: {
        path: mapsApi.maps.SymbolPath.CIRCLE,
        scale: 9,
        fillColor: '#22c55e',
        fillOpacity: 1,
        strokeColor: '#ffffff',
        strokeWeight: 2.5,
      },
      label: { text: 'S', color: '#0b0b10', fontSize: '11px', fontWeight: '800' },
      zIndex: 7,
    })
    attachHoverTooltip(start, `<div class="text-xs font-medium">Start · ${timeLabel(first.recorded_at)}</div>`)
    terminalMarkers.push(start)

    const end = new mapsApi.maps.Marker({
      position: { lat: last.lat, lng: last.lng },
      map,
      icon: {
        path: mapsApi.maps.SymbolPath.CIRCLE,
        scale: 9,
        fillColor: '#f43f5e',
        fillOpacity: 1,
        strokeColor: '#ffffff',
        strokeWeight: 2.5,
      },
      label: { text: 'E', color: '#0b0b10', fontSize: '11px', fontWeight: '800' },
      zIndex: 8,
    })
    attachHoverTooltip(end, `<div class="text-xs font-medium">End · ${timeLabel(last.recorded_at)}</div>`)
    terminalMarkers.push(end)

    for (const point of groupPoints) bounds.extend({ lat: point.lat, lng: point.lng })

    for (const point of decimatePoints(groupPoints)) {
      const dot = new mapsApi.maps.Marker({
        position: { lat: point.lat, lng: point.lng },
        map,
        icon: { path: mapsApi.maps.SymbolPath.CIRCLE, scale: 3, fillColor: color, fillOpacity: 0.85, strokeWeight: 0 },
        zIndex: 3,
      })
      attachHoverTooltip(dot, `<div class="text-xs font-medium">${timeLabel(point.recorded_at)}</div>`)
      pointMarkers.push(dot)
    }
  }

  map.fitBounds(bounds, 72)
  mapsApi.maps.event.addListenerOnce(map, 'idle', () => {
    if (map && (map.getZoom() ?? 0) > 16) map.setZoom(16)
  })
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

onMounted(async () => {
  loadEmployees()

  if (!apiKeyConfigured) {
    mapError.value = 'Google Maps API key is not configured. Contact an administrator.'
    return
  }

  try {
    mapsApi = await loadGoogleMaps()
    map = new mapsApi.maps.Map(mapContainer.value!, {
      center: { lat: 23.6144, lng: 58.5922 },
      zoom: 9,
      styles: LIGHT_MAP_STYLE,
      disableDefaultUI: true,
      zoomControl: true,
      clickableIcons: false,
      restriction: { latLngBounds: { north: 26.6, south: 16.6, east: 59.95, west: 52.1 }, strictBounds: false },
    })
    hoverInfoWindow = new mapsApi.maps.InfoWindow({ disableAutoPan: true })
    await loadTrail()
  } catch {
    mapError.value = 'The map could not be loaded. Check the Google Maps API key and network access.'
  }
})

watch(selectedDate, loadTrail)
</script>

<template>
  <AppShell :title="`${employee?.name ?? 'Employee'} histories`" subtitle="Daily routes by shift" :back-to="`/employees/${employeeId}`">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="trailLoading" @click="loadTrail">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="trailLoading" />
        Refresh
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

    <div v-if="trail && trail.points.length > 0" class="mb-4 grid gap-3 sm:grid-cols-3">
      <StatCard icon="route" label="Distance (selected shift)" :value="formatDistance(selectedDistanceM)" />
      <StatCard icon="map-pin" label="Points recorded" :value="String(trail.points_count)" />
      <StatCard icon="speed" label="Average speed" :value="formatSpeed(trail.average_speed_mps)" />
    </div>

    <section class="surface-flat relative h-[60vh] min-h-[420px] overflow-hidden">
      <div ref="mapContainer" class="!absolute !inset-0 bg-surface-sunken" />
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
