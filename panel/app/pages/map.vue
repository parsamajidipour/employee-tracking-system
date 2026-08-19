<script setup lang="ts">
import { getEmployeeMarkerOverlayCtor, type EmployeeMarkerOverlayInstance } from '~/utils/mapMarker'

const { positions, now, stalenessBucket } = usePositions()
const { load: loadGoogleMaps, mapId, apiKeyConfigured } = useGoogleMaps()

const mapContainer = ref<HTMLDivElement | null>(null)
const mapError = ref<string | null>(null)
let map: google.maps.Map | undefined
let MarkerOverlay: ReturnType<typeof getEmployeeMarkerOverlayCtor> | undefined
const markers = new Map<number, EmployeeMarkerOverlayInstance>()

const selectedEmployeeId = ref<number | null>(null)

const selectedPosition = computed(
  () => positions.value.find((position) => position.employee_id === selectedEmployeeId.value) ?? null,
)

const OFFLINE_COLOR = '#f59e0b'

function employeeColor(employeeId: number): string {
  return `hsl(${(employeeId * 137.508) % 360} 78% 46%)`
}

function markerColor(employeeId: number, recordedAt: string): string {
  return stalenessBucket(recordedAt) === 'offline' ? OFFLINE_COLOR : employeeColor(employeeId)
}

function selectEmployee(employeeId: number) {
  selectedEmployeeId.value = employeeId
}

function closeDetail() {
  selectedEmployeeId.value = null
}

function focusEmployee(employeeId: number) {
  const position = positions.value.find((p) => p.employee_id === employeeId)
  if (position && map) {
    map.panTo({ lat: position.lat, lng: position.lng })
    map.setZoom(Math.max(map.getZoom() ?? 11, 14))
  }
  selectEmployee(employeeId)
}

function syncMarkers() {
  if (!map || !MarkerOverlay) return

  const seen = new Set<number>()

  for (const position of positions.value) {
    seen.add(position.employee_id)
    const color = markerColor(position.employee_id, position.recorded_at)
    let marker = markers.get(position.employee_id)

    if (!marker) {
      marker = new MarkerOverlay(map, { lat: position.lat, lng: position.lng }, position.name, () =>
        selectEmployee(position.employee_id),
      )
      markers.set(position.employee_id, marker)
    } else {
      marker.setName(position.name)
      marker.moveTo({ lat: position.lat, lng: position.lng })
    }

    marker.setColor(color)
  }

  for (const [employeeId, marker] of markers) {
    if (!seen.has(employeeId)) {
      marker.destroy()
      markers.delete(employeeId)
      if (selectedEmployeeId.value === employeeId) closeDetail()
    }
  }
}

watch(positions, syncMarkers, { deep: true })
watch(now, syncMarkers)

onMounted(async () => {
  if (!apiKeyConfigured) {
    mapError.value = 'Google Maps API key is not configured. Contact an administrator.'
    return
  }

  try {
    const g = await loadGoogleMaps()
    MarkerOverlay = getEmployeeMarkerOverlayCtor(g)

    map = new g.maps.Map(mapContainer.value!, {
      center: { lat: 23.6144, lng: 58.5922 },
      zoom: 11,
      mapId,
      disableDefaultUI: true,
      zoomControl: true,
      clickableIcons: false,
      restriction: {
        latLngBounds: { north: 26.6, south: 16.6, east: 59.95, west: 52.1 },
        strictBounds: false,
      },
    })

    syncMarkers()

    const stop = watch(
      positions,
      (snapshot) => {
        if (snapshot.length === 0 || !map) return
        const bounds = new g.maps.LatLngBounds()
        for (const position of snapshot) bounds.extend({ lat: position.lat, lng: position.lng })
        map.fitBounds(bounds, 60)
        stop()
      },
      { deep: true },
    )
  } catch {
    mapError.value = 'The map could not be loaded. Check the Google Maps API key and network access.'
  }
})

onUnmounted(() => {
  for (const marker of markers.values()) marker.destroy()
  markers.clear()
})
</script>

<template>
  <AppShell title="Live map" full-bleed>
    <div ref="mapContainer" class="!absolute !inset-0 bg-surface-muted"></div>

    <div v-if="mapError" class="absolute left-4 right-4 top-4 z-10 sm:right-auto sm:w-[min(400px,calc(100%-2rem))]">
      <InlineAlert>{{ mapError }}</InlineAlert>
    </div>

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

        <dl class="mt-3 space-y-2 text-sm">
          <div class="flex items-baseline justify-between gap-3">
            <dt class="muted">Status</dt>
            <dd :class="selectedPosition && stalenessBucket(selectedPosition.recorded_at) === 'online' ? 'text-state-success' : 'text-state-warning'">
              {{ selectedPosition ? stalenessBucket(selectedPosition.recorded_at) : 'offline' }}
            </dd>
          </div>
          <div v-if="selectedPosition" data-testid="detail-last-update" class="flex items-baseline justify-between gap-3">
            <dt class="muted">Last update</dt>
            <dd class="tabular">{{ new Date(selectedPosition.recorded_at).toLocaleTimeString() }}</dd>
          </div>
        </dl>
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
