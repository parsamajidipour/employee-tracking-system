<script setup lang="ts">
import { GeoJSONSource, LngLatBounds, Map as MapLibreMap, addProtocol, setWorkerUrl } from 'maplibre-gl'
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'
import { Protocol as PMTilesProtocol } from 'pmtiles'
import { layers as protomapsLayers, namedFlavor } from '@protomaps/basemaps'

interface Employee { id: number; name: string }
interface HistoryRow { date: string; distance_m: string | number; started_at: string; ended_at: string; points_count: number }
interface TrailPoint { lng: number; lat: number; distance_m: number; accuracy_m: number | null; speed_mps: number | null; heading_deg: number | null; battery_pct: number | null; recorded_at: string }
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
  points: TrailPoint[]
}

const route = useRoute()
const employeeId = Number(route.params.id)
const employee = ref<Employee | null>(null)
const histories = ref<HistoryRow[]>([])
const selectedDate = ref<string | null>(null)
const trail = ref<Trail | null>(null)
const loading = ref(true)
const trailLoading = ref(false)
const error = ref<string | null>(null)
const mapContainer = ref<HTMLDivElement | null>(null)
const basemapError = ref<string | null>(null)
let map: MapLibreMap | undefined

const totalDistance = computed(() => histories.value.reduce((sum, row) => sum + Number(row.distance_m), 0))
const activeDuration = computed(() => {
  if (!trail.value?.first_point_at || !trail.value.last_point_at) return '—'
  const minutes = Math.max(0, Math.round((new Date(trail.value.last_point_at).getTime() - new Date(trail.value.first_point_at).getTime()) / 60000))
  return `${Math.floor(minutes / 60)}h ${minutes % 60}m`
})

function distance(value: string | number) { return `${(Number(value) / 1000).toFixed(2)} km` }
function speed(value: number | null | undefined) { return value == null ? '—' : `${(value * 3.6).toFixed(1)} km/h` }
function time(value: string | null | undefined) { return value ? new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '—' }

function renderTrail() {
  if (!map?.isStyleLoaded()) return
  const points = trail.value?.points ?? []
  ;(map.getSource('history-trail') as GeoJSONSource)?.setData({
    type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: points.map(point => [point.lng, point.lat]) },
  })
  ;(map.getSource('history-terminals') as GeoJSONSource)?.setData({
    type: 'FeatureCollection',
    features: points.length < 1 ? [] : [
      { type: 'Feature', properties: { kind: 'Start' }, geometry: { type: 'Point', coordinates: [points[0]!.lng, points[0]!.lat] } },
      { type: 'Feature', properties: { kind: 'End' }, geometry: { type: 'Point', coordinates: [points.at(-1)!.lng, points.at(-1)!.lat] } },
    ],
  })
  if (points.length) {
    const bounds = points.slice(1).reduce((box, point) => box.extend([point.lng, point.lat]), new LngLatBounds([points[0]!.lng, points[0]!.lat], [points[0]!.lng, points[0]!.lat]))
    map.fitBounds(bounds, { padding: 72, maxZoom: 16, duration: 500 })
  }
}

async function selectDay(date: string) {
  selectedDate.value = date
  trailLoading.value = true
  try {
    trail.value = await apiFetch<Trail>(`/api/v1/employees/${employeeId}/trail?date=${encodeURIComponent(date)}`)
    error.value = null
    renderTrail()
  } catch {
    error.value = 'Could not load activity for this day.'
  } finally {
    trailLoading.value = false
  }
}

async function load() {
  loading.value = true
  try {
    const [employees, rows] = await Promise.all([
      apiFetch<Employee[]>('/api/v1/employees'),
      apiFetch<HistoryRow[]>(`/api/v1/employees/${employeeId}/histories`),
    ])
    employee.value = employees.find(item => item.id === employeeId) ?? null
    histories.value = rows
    if (rows[0]) await selectDay(rows[0].date)
  } catch {
    error.value = 'Could not load location histories.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  try {
    const basemapCheck = await fetch(`${apiOrigin()}/api/basemap/oman.pmtiles`, { method: 'HEAD' })
    if (!basemapCheck.ok) basemapError.value = 'Map tiles are unavailable on the server (basemap file missing). Contact an administrator.'
  } catch {
    basemapError.value = 'Could not reach the map tile server.'
  }

  setWorkerUrl(maplibreWorkerUrl)
  addProtocol('pmtiles', new PMTilesProtocol().tile)
  map = new MapLibreMap({
    container: mapContainer.value!,
    style: {
      version: 8,
      sources: { protomaps: { type: 'vector', url: `pmtiles://${apiOrigin()}/api/basemap/oman.pmtiles`, attribution: '© OpenStreetMap contributors' } },
      layers: protomapsLayers('protomaps', namedFlavor('light'), { lang: 'en' }),
    },
    center: [58.5922, 23.6144], zoom: 10,
    maxBounds: [[52.1, 16.6], [59.95, 26.6]],
  })
  map.on('error', (e) => {
    if (!basemapError.value) basemapError.value = 'The map could not be loaded.'
    console.error('MapLibre error', e.error)
  })
  map.on('load', () => {
    map?.addSource('history-trail', { type: 'geojson', data: { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: [] } } })
    map?.addLayer({ id: 'history-trail-line', type: 'line', source: 'history-trail', paint: { 'line-color': '#2f9ec0', 'line-width': 5, 'line-opacity': 0.9 }, layout: { 'line-cap': 'round', 'line-join': 'round' } })
    map?.addSource('history-terminals', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } })
    map?.addLayer({ id: 'history-terminal-points', type: 'circle', source: 'history-terminals', paint: { 'circle-radius': 7, 'circle-color': ['match', ['get', 'kind'], 'Start', '#3fa98a', '#d2635e'], 'circle-stroke-color': '#ffffff', 'circle-stroke-width': 3 } })
    renderTrail()
  })
  await load()
})

onUnmounted(() => map?.remove())
</script>

<template>
  <AppShell :title="`${employee?.name ?? 'Employee'} histories`" subtitle="Daily routes and complete tracked activity">
    <template #actions><Button variant="secondary" :to="`/employees/${employeeId}`">Employee shifts</Button></template>
    <InlineAlert v-if="error" class="mb-4">{{ error }}</InlineAlert>

    <div class="mb-5 grid gap-3 sm:grid-cols-3">
      <div class="card p-4"><p class="text-xs text-ink-faint">Total retained distance</p><strong class="mt-1 block text-xl tabular-nums">{{ distance(totalDistance) }}</strong></div>
      <div class="card p-4"><p class="text-xs text-ink-faint">Tracked days</p><strong class="mt-1 block text-xl tabular-nums">{{ histories.length }}</strong></div>
      <div class="card p-4"><p class="text-xs text-ink-faint">Selected activity</p><strong class="mt-1 block text-xl tabular-nums">{{ selectedDate ?? '—' }}</strong></div>
    </div>

    <section class="relative mb-4 h-[320px] overflow-hidden rounded-card border border-hairline bg-surface shadow-card sm:h-[560px]">
      <div ref="mapContainer" class="absolute inset-0" />
      <div v-if="basemapError" class="absolute inset-x-4 top-4 z-10">
        <InlineAlert>{{ basemapError }}</InlineAlert>
      </div>
      <aside class="floating absolute right-4 top-4 hidden w-[min(340px,calc(100%-2rem))] p-4 sm:block">
        <div class="mb-4 flex items-center justify-between gap-3">
          <div><p class="overline">Activity card</p><h2>{{ selectedDate ?? 'Select a day' }}</h2></div>
          <span v-if="trailLoading" class="text-xs text-ink-faint">Loading…</span>
        </div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
          <div><p class="text-xs text-ink-faint">Distance</p><strong class="tabular-nums">{{ distance(trail?.distance_m ?? 0) }}</strong></div>
          <div><p class="text-xs text-ink-faint">Active duration</p><strong class="tabular-nums">{{ activeDuration }}</strong></div>
          <div><p class="text-xs text-ink-faint">First point</p><strong class="tabular-nums">{{ time(trail?.first_point_at) }}</strong></div>
          <div><p class="text-xs text-ink-faint">Last point</p><strong class="tabular-nums">{{ time(trail?.last_point_at) }}</strong></div>
          <div><p class="text-xs text-ink-faint">Average speed</p><strong class="tabular-nums">{{ speed(trail?.average_speed_mps) }}</strong></div>
          <div><p class="text-xs text-ink-faint">Maximum speed</p><strong class="tabular-nums">{{ speed(trail?.max_speed_mps) }}</strong></div>
          <div><p class="text-xs text-ink-faint">GPS accuracy</p><strong class="tabular-nums">{{ trail?.average_accuracy_m == null ? '—' : `${trail.average_accuracy_m.toFixed(1)} m` }}</strong></div>
          <div><p class="text-xs text-ink-faint">Recorded points</p><strong class="tabular-nums">{{ trail?.points_count ?? 0 }}</strong></div>
        </div>
        <div class="mt-4 flex gap-4 border-t border-hairline pt-3 text-xs text-ink-soft"><span><i class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-success" />Start</span><span><i class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-danger" />End</span></div>
      </aside>
    </section>

    <div class="card mb-5 p-4 sm:hidden">
      <div class="mb-4 flex items-center justify-between gap-3">
        <div><p class="overline">Activity card</p><h2>{{ selectedDate ?? 'Select a day' }}</h2></div>
        <span v-if="trailLoading" class="text-xs text-ink-faint">Loading…</span>
      </div>
      <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
        <div><p class="text-xs text-ink-faint">Distance</p><strong class="tabular-nums">{{ distance(trail?.distance_m ?? 0) }}</strong></div>
        <div><p class="text-xs text-ink-faint">Active duration</p><strong class="tabular-nums">{{ activeDuration }}</strong></div>
        <div><p class="text-xs text-ink-faint">First point</p><strong class="tabular-nums">{{ time(trail?.first_point_at) }}</strong></div>
        <div><p class="text-xs text-ink-faint">Last point</p><strong class="tabular-nums">{{ time(trail?.last_point_at) }}</strong></div>
        <div><p class="text-xs text-ink-faint">Average speed</p><strong class="tabular-nums">{{ speed(trail?.average_speed_mps) }}</strong></div>
        <div><p class="text-xs text-ink-faint">Maximum speed</p><strong class="tabular-nums">{{ speed(trail?.max_speed_mps) }}</strong></div>
        <div><p class="text-xs text-ink-faint">GPS accuracy</p><strong class="tabular-nums">{{ trail?.average_accuracy_m == null ? '—' : `${trail.average_accuracy_m.toFixed(1)} m` }}</strong></div>
        <div><p class="text-xs text-ink-faint">Recorded points</p><strong class="tabular-nums">{{ trail?.points_count ?? 0 }}</strong></div>
      </div>
      <div class="mt-4 flex gap-4 border-t border-hairline pt-3 text-xs text-ink-soft"><span><i class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-success" />Start</span><span><i class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-danger" />End</span></div>
    </div>

    <Table :headers="['Date', 'Started', 'Ended', 'Points', 'Distance', '']" :loading="loading" :is-empty="histories.length === 0" empty-message="No retained tracking history for this employee.">
      <tr v-for="row in histories" :key="row.date" class="cursor-pointer text-ink transition-colors hover:bg-surface-muted" :class="selectedDate === row.date ? 'bg-primary-soft' : ''" @click="selectDay(row.date)">
        <td class="px-5 py-3 font-medium">{{ row.date }}</td><td class="px-5 py-3 tabular-nums">{{ time(row.started_at) }}</td><td class="px-5 py-3 tabular-nums">{{ time(row.ended_at) }}</td><td class="px-5 py-3 tabular-nums">{{ row.points_count }}</td><td class="px-5 py-3 font-semibold tabular-nums">{{ distance(row.distance_m) }}</td><td class="px-5 py-3 text-right text-primary-strong">View route</td>
      </tr>
    </Table>
  </AppShell>
</template>
