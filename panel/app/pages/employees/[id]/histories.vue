<script setup lang="ts">
import { bearingBetween, shiftColor } from '~/utils/mapMarker'

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
interface TrailShift { index: number; source: string; start: string; end: string; label: string; distance_m: number }
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

const UNASSIGNED_COLOR = '#94a3b8'

function todayLocalDate(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

const route = useRoute()
const employeeId = Number(route.params.id)

const { data: employeesData, load: loadEmployees } = useEmployees()
const employee = computed(() => employeesData.value?.find((item) => item.id === employeeId) ?? null)

const { load: loadGoogleMaps, mapId, apiKeyConfigured } = useGoogleMaps()

const selectedDate = ref(todayLocalDate())
const selectedShift = ref<'all' | number>('all')
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

function clearOverlays() {
  for (const line of polylines) line.setMap(null)
  for (const marker of terminalMarkers) marker.setMap(null)
  for (const marker of pointMarkers) marker.setMap(null)
  polylines = []
  terminalMarkers = []
  pointMarkers = []
}

function visiblePoints(): TrailPoint[] {
  const points = trail.value?.points ?? []
  if (selectedShift.value === 'all') return points
  return points.filter((point) => point.shift_index === selectedShift.value)
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
      strokeWeight: 4,
      map,
    })
    polylines.push(line)

    const first = groupPoints[0]!
    const last = groupPoints.at(-1)!

    const start = new mapsApi.maps.Marker({
      position: { lat: first.lat, lng: first.lng },
      map,
      icon: { path: mapsApi.maps.SymbolPath.CIRCLE, scale: 7, fillColor: color, fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 },
      zIndex: 5,
    })
    attachHoverTooltip(start, `<div class="text-xs font-medium">Start · ${timeLabel(first.recorded_at)}</div>`)
    terminalMarkers.push(start)

    const bearing = groupPoints.length > 1 ? bearingBetween(groupPoints.at(-2)!, last) : 0
    const end = new mapsApi.maps.Marker({
      position: { lat: last.lat, lng: last.lng },
      map,
      icon: {
        path: mapsApi.maps.SymbolPath.FORWARD_CLOSED_ARROW,
        scale: 4.5,
        rotation: bearing,
        fillColor: color,
        fillOpacity: 1,
        strokeColor: '#fff',
        strokeWeight: 1.5,
      },
      zIndex: 6,
    })
    attachHoverTooltip(end, `<div class="text-xs font-medium">End · ${timeLabel(last.recorded_at)}</div>`)
    terminalMarkers.push(end)

    for (const point of groupPoints) {
      const dot = new mapsApi.maps.Marker({
        position: { lat: point.lat, lng: point.lng },
        map,
        icon: { path: mapsApi.maps.SymbolPath.CIRCLE, scale: 3, fillColor: color, fillOpacity: 0.85, strokeWeight: 0 },
        zIndex: 3,
      })
      attachHoverTooltip(dot, `<div class="text-xs font-medium">${timeLabel(point.recorded_at)}</div>`)
      pointMarkers.push(dot)
      bounds.extend({ lat: point.lat, lng: point.lng })
    }
  }

  map.fitBounds(bounds, 72)
}

async function loadTrail() {
  trailLoading.value = true
  try {
    trail.value = await apiFetch<Trail>(`/api/v1/employees/${employeeId}/trail?date=${encodeURIComponent(selectedDate.value)}`)
    if (selectedShift.value !== 'all' && !trail.value.shifts.some((shift) => shift.index === selectedShift.value)) {
      selectedShift.value = 'all'
    }
    error.value = null
    renderTrail()
  } catch {
    error.value = 'Could not load activity for this day.'
  } finally {
    trailLoading.value = false
  }
}

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
      zoom: 10,
      mapId,
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
  <AppShell :title="`${employee?.name ?? 'Employee'} histories`" subtitle="Daily routes by shift">
    <template #actions><Button variant="secondary" :to="`/employees/${employeeId}`">Employee shifts</Button></template>
    <InlineAlert v-if="error" class="mb-4">{{ error }}</InlineAlert>

    <form class="card mb-4 flex flex-wrap items-end gap-4 p-4" @submit.prevent>
      <div>
        <label for="history-date" class="mb-1 block text-xs font-medium text-ink-soft">Date</label>
        <input
          id="history-date"
          v-model="selectedDate"
          type="date"
          :max="todayLocalDate()"
          class="field w-44"
        />
      </div>
      <Select v-model="selectedShift" label="Shift" class="w-56">
        <option value="all">All shifts</option>
        <option v-for="shift in trail?.shifts ?? []" :key="shift.index" :value="shift.index">
          {{ shift.label }}
        </option>
      </Select>
      <span v-if="trailLoading" class="pb-2.5 text-xs text-ink-faint">Loading…</span>
    </form>

    <section class="relative h-[60vh] min-h-[420px] overflow-hidden rounded-card border border-hairline bg-surface shadow-card">
      <div ref="mapContainer" class="!absolute !inset-0 bg-surface-muted" />
      <div v-if="mapError" class="absolute inset-x-4 top-4 z-10">
        <InlineAlert>{{ mapError }}</InlineAlert>
      </div>
      <p
        v-if="!trailLoading && trail && trail.points.length === 0"
        class="absolute inset-x-4 top-4 z-10 rounded-control border border-hairline bg-surface px-4 py-2.5 text-sm text-ink-soft shadow-raised"
      >
        No tracked activity for this day.
      </p>
    </section>
  </AppShell>
</template>
