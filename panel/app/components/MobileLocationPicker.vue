<script setup lang="ts">
const props = defineProps<{
  open: boolean
  lat: number | null
  lng: number | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm', value: { lat: number; lng: number }): void
}>()

const { t } = useI18n()
const draftLat = ref<number | null>(props.lat)
const draftLng = ref<number | null>(props.lng)
const latitude = ref(props.lat?.toString() ?? '')
const longitude = ref(props.lng?.toString() ?? '')
const mapUrl = ref('')
const optionsOpen = ref(false)
const resolvingUrl = ref(false)
const locating = ref(false)
const error = ref<string | null>(null)
let previousBodyOverflow = ''

watch(
  () => props.open,
  (open) => {
    if (!open) {
      document.body.style.overflow = previousBodyOverflow
      return
    }
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    draftLat.value = props.lat
    draftLng.value = props.lng
    latitude.value = props.lat?.toString() ?? ''
    longitude.value = props.lng?.toString() ?? ''
    mapUrl.value = ''
    optionsOpen.value = false
    error.value = null
  },
)

onBeforeUnmount(() => {
  document.body.style.overflow = previousBodyOverflow
})

function select(position: { lat: number; lng: number }) {
  draftLat.value = position.lat
  draftLng.value = position.lng
  latitude.value = String(position.lat)
  longitude.value = String(position.lng)
  error.value = null
}

function applyCoordinates() {
  const lat = Number(latitude.value)
  const lng = Number(longitude.value)
  if (!Number.isFinite(lat) || !Number.isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
    error.value = t('cases.new.invalidCoordinates')
    return
  }
  select({ lat: Number(lat.toFixed(6)), lng: Number(lng.toFixed(6)) })
  optionsOpen.value = false
}

async function applyMapUrl() {
  if (!mapUrl.value.trim()) {
    error.value = t('cases.new.invalidMapUrl')
    return
  }
  resolvingUrl.value = true
  error.value = null
  try {
    const position = await apiFetch<{ lat: number; lng: number }>('/api/v1/resolve-map-url', {
      method: 'POST',
      body: { url: mapUrl.value.trim() },
    })
    select(position)
    optionsOpen.value = false
  } catch {
    error.value = t('cases.new.invalidMapUrl')
  } finally {
    resolvingUrl.value = false
  }
}

function useMyLocation() {
  if (!navigator.geolocation) {
    error.value = t('cases.new.geolocationFailed')
    return
  }
  locating.value = true
  error.value = null
  navigator.geolocation.getCurrentPosition(
    (position) => {
      select({
        lat: Number(position.coords.latitude.toFixed(6)),
        lng: Number(position.coords.longitude.toFixed(6)),
      })
      locating.value = false
      optionsOpen.value = false
    },
    () => {
      locating.value = false
      error.value = t('cases.new.geolocationFailed')
    },
    { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 },
  )
}

function confirm() {
  if (draftLat.value === null || draftLng.value === null) return
  emit('confirm', { lat: draftLat.value, lng: draftLng.value })
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[100] bg-surface lg:hidden">
      <LocationPicker :lat="draftLat" :lng="draftLng" :show-hint="false" @location-selected="select" />

      <button type="button" class="safe-start-3 surface absolute top-[max(0.75rem,env(safe-area-inset-top))] grid h-11 w-11 place-items-center rounded-full shadow-lg" :aria-label="t('common.close')" @click="emit('close')">
        <Icon name="close" class="h-5 w-5" />
      </button>
      <button type="button" class="safe-end-3 elevated-overlay absolute top-[max(0.75rem,env(safe-area-inset-top))] flex h-11 items-center gap-2 rounded-full bg-surface px-4 text-sm font-semibold" @click="optionsOpen = !optionsOpen">
        <Icon name="navigation" class="h-5 w-5" />
        {{ t('cases.new.mapOptions') }}
      </button>

      <div v-if="error && !optionsOpen" class="safe-inset-x-3 absolute top-[calc(5rem+env(safe-area-inset-top))] rounded-md bg-state-danger px-3 py-2 text-xs text-white shadow-lg">{{ error }}</div>

      <section v-if="optionsOpen" class="safe-inset-x-3 elevated-overlay absolute top-[calc(5rem+env(safe-area-inset-top))] max-h-[calc(100dvh_-_11rem_-_env(safe-area-inset-top)_-_env(safe-area-inset-bottom))] overflow-y-auto bg-surface p-4">
        <p class="mb-3 text-sm font-bold text-ink">{{ t('cases.new.mapOptions') }}</p>
        <InlineAlert v-if="error" class="mb-3">{{ error }}</InlineAlert>

        <TextInput v-model="mapUrl" type="url" :label="t('cases.new.mapUrl')" :placeholder="t('cases.new.mapUrlPlaceholder')" />
        <Button type="button" size="sm" class="mt-2 w-full" :loading="resolvingUrl" @click="applyMapUrl">{{ t('cases.new.useMapUrl') }}</Button>

        <div class="my-4 border-t border-hairline" />
        <p class="mb-2 text-xs font-medium text-ink-soft">{{ t('cases.new.coordinates') }}</p>
        <div class="grid grid-cols-2 gap-2">
          <input v-model="latitude" inputmode="decimal" class="field min-w-0" :aria-label="t('cases.new.latitude')" :placeholder="t('cases.new.latitude')" />
          <input v-model="longitude" inputmode="decimal" class="field min-w-0" :aria-label="t('cases.new.longitude')" :placeholder="t('cases.new.longitude')" />
        </div>
        <Button type="button" variant="secondary" class="mt-2 w-full" @click="applyCoordinates">{{ t('cases.new.useCoordinates') }}</Button>

        <div class="my-4 border-t border-hairline" />
        <Button type="button" variant="secondary" class="w-full" :loading="locating" @click="useMyLocation">
          <Icon name="navigation" class="h-4 w-4" />
          {{ locating ? t('cases.new.locatingMe') : t('cases.new.useMyLocation') }}
        </Button>
      </section>

      <div class="safe-inset-x-3 absolute bottom-[max(0.75rem,env(safe-area-inset-bottom))]">
        <Button type="button" class="w-full shadow-xl" :disabled="draftLat === null || draftLng === null" @click="confirm">
          {{ t('cases.new.confirmLocation') }}
        </Button>
      </div>
    </div>
  </Teleport>
</template>
