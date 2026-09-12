<script setup lang="ts">
import type { NewCasePayload } from '~/composables/useCases'
import { CASE_PRIORITIES, casePriorityLabel } from '~/utils/caseStatus'

const toast = useToast()

const submitting = ref(false)
const formError = ref<string | null>(null)
const locationLookupState = ref<'idle' | 'loading' | 'error'>('idle')
const locationManuallyEdited = ref(false)
let locationLookupTimer: ReturnType<typeof setTimeout> | undefined
let locationLookupSequence = 0

const form = reactive({
  reference_no: '',
  title: '',
  property_address: '',
  lat: null as number | null,
  lng: null as number | null,
  priority: 'normal' as (typeof CASE_PRIORITIES)[number],
  notes: '',
})

const hasLocation = computed(() => form.lat !== null && form.lng !== null)

const canSubmit = computed(() => form.reference_no.trim() !== '' && form.title.trim() !== '' && hasLocation.value)
const locationHint = computed(() => {
  if (locationLookupState.value === 'loading') return 'Finding the location from the map…'
  if (locationLookupState.value === 'error') return 'Address not found — move the map again or enter it manually.'
  return 'Filled automatically from the map; you can edit it.'
})

async function lookupLocation(position: { lat: number; lng: number }, sequence: number) {
  try {
    const query = new URLSearchParams({
      lat: String(position.lat),
      lng: String(position.lng),
    })
    const response = await apiFetch<{ location: string | null }>(`/api/v1/reverse-geocode?${query}`)

    if (sequence !== locationLookupSequence || locationManuallyEdited.value) return

    if (response.location) {
      form.property_address = response.location
      locationLookupState.value = 'idle'
    } else {
      locationLookupState.value = 'error'
    }
  } catch {
    if (sequence === locationLookupSequence && !locationManuallyEdited.value) {
      locationLookupState.value = 'error'
    }
  }
}

function selectLocation(position: { lat: number; lng: number }) {
  form.lat = position.lat
  form.lng = position.lng
  form.property_address = ''
  formError.value = null
  locationManuallyEdited.value = false
  locationLookupState.value = 'loading'

  locationLookupSequence += 1
  const sequence = locationLookupSequence
  if (locationLookupTimer) clearTimeout(locationLookupTimer)
  locationLookupTimer = setTimeout(() => {
    void lookupLocation(position, sequence)
  }, 1100)
}

function markLocationManuallyEdited() {
  locationManuallyEdited.value = true
  locationLookupState.value = 'idle'
}

async function submit() {
  if (locationLookupState.value === 'loading') {
    formError.value = 'Wait for the location lookup to finish.'
    return
  }

  if (!canSubmit.value) {
    formError.value = 'Report number, customer name, and a location on the map are required.'
    return
  }

  submitting.value = true
  formError.value = null
  try {
    const payload: NewCasePayload = {
      reference_no: form.reference_no,
      title: form.title,
      property_address: form.property_address || undefined,
      lat: form.lat as number,
      lng: form.lng as number,
      priority: form.priority,
      notes: form.notes || undefined,
    }
    const created = await createCase(payload)
    toast.success('Case created. Assign it to a surveyor from here.')
    await navigateTo(`/cases/${created.id}`)
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Could not create case — check the fields.')
    toast.error(formError.value)
  } finally {
    submitting.value = false
  }
}

onBeforeUnmount(() => {
  if (locationLookupTimer) clearTimeout(locationLookupTimer)
})
</script>

<template>
  <AppShell title="New case" subtitle="Create an inspection case" back-to="/cases" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" to="/cases" aria-label="Cancel case creation">
        <Icon name="close" class="h-3.5 w-3.5 sm:hidden" />
        <span class="hidden sm:inline">Cancel</span>
      </Button>
      <Button size="sm" :loading="submitting" aria-label="Create case" @click="submit">
        <Icon v-if="!submitting" name="plus" class="h-3.5 w-3.5 sm:hidden" />
        <span class="hidden sm:inline">{{ submitting ? 'Creating…' : 'Create case' }}</span>
      </Button>
    </template>

    <div class="flex h-full min-h-0 flex-col gap-3 overflow-y-auto p-3 sm:gap-4 sm:p-5 lg:grid lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)] lg:overflow-hidden">
      <div class="flex flex-none flex-col gap-3 sm:gap-4 lg:min-h-0 lg:overflow-y-auto lg:pr-1">
        <InlineAlert v-if="formError" class="!mb-0 flex-none">{{ formError }}</InlineAlert>

        <Card class="flex-none" icon="briefcase" title="Case details" subtitle="Report, customer, and priority information">
          <form class="space-y-3.5" @submit.prevent="submit">
            <TextInput v-model="form.reference_no" label="Report no." placeholder="e.g. RPT-1042" required />
            <TextInput v-model="form.title" label="Customer name" placeholder="e.g. Ahmed Al Balushi" required />
            <TextInput
              v-model="form.property_address"
              label="Location"
              :placeholder="locationLookupState === 'loading' ? 'Finding location…' : 'Move the map to fill this automatically'"
              :hint="locationHint"
              @update:model-value="markLocationManuallyEdited"
            />

            <Select v-model="form.priority" label="Priority">
              <option v-for="priority in CASE_PRIORITIES" :key="priority" :value="priority">
                {{ casePriorityLabel(priority) }}
              </option>
            </Select>

            <div>
              <label for="case-notes" class="mb-1.5 block text-[12px] font-medium text-ink-soft">Notes</label>
              <textarea
                id="case-notes"
                v-model="form.notes"
                rows="4"
                placeholder="Anything a surveyor should know before accepting this case"
                class="field h-auto resize-none py-2.5"
              />
            </div>
          </form>
        </Card>

        <Card class="flex-none" icon="user-circle" title="What happens next">
          <ol class="space-y-2.5 text-[12.5px] text-ink-soft">
            <li class="flex gap-2.5">
              <span class="grid h-5 w-5 flex-none place-items-center rounded-full bg-primary-soft text-[11px] font-bold text-primary-strong">1</span>
              Every active employee is notified that this case exists as soon as it is created.
            </li>
            <li class="flex gap-2.5">
              <span class="grid h-5 w-5 flex-none place-items-center rounded-full bg-primary-soft text-[11px] font-bold text-primary-strong">2</span>
              You assign it to a surveyor on the case page — nearest on-shift surveyors are ranked for you there.
            </li>
            <li class="flex gap-2.5">
              <span class="grid h-5 w-5 flex-none place-items-center rounded-full bg-primary-soft text-[11px] font-bold text-primary-strong">3</span>
              The surveyor accepts and schedules it, and the case page updates here live.
            </li>
          </ol>
        </Card>
      </div>

      <Card class="flex-none lg:min-h-0" icon="map-pin" title="Location on map" :subtitle="hasLocation ? 'Drag the pin to fine-tune' : 'Required — click the map to drop the pin'" flush>
        <template #actions>
          <Badge :variant="hasLocation ? 'success' : 'warning'">
            {{ hasLocation ? `${form.lat!.toFixed(5)}, ${form.lng!.toFixed(5)}` : 'Not set' }}
          </Badge>
        </template>
        <div class="h-[360px] min-h-[360px] sm:h-[420px] sm:min-h-[420px] lg:h-full">
          <LocationPicker :lat="form.lat" :lng="form.lng" @location-selected="selectLocation" />
        </div>
      </Card>
    </div>
  </AppShell>
</template>
