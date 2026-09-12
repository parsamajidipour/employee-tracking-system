<script setup lang="ts">
import type { NewCasePayload } from '~/composables/useCases'
import { CASE_PRIORITIES } from '~/utils/caseStatus'

const toast = useToast()
const { t } = useI18n()
const { number } = useLocalizedFormat()

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
  if (locationLookupState.value === 'loading') return t('cases.new.locationFindingHint')
  if (locationLookupState.value === 'error') return t('cases.new.locationNotFound')
  return t('cases.new.locationFilledHint')
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
    formError.value = t('cases.new.waitForLocation')
    return
  }

  if (!canSubmit.value) {
    formError.value = t('cases.new.requiredFields')
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
    toast.success(t('cases.new.created'))
    await navigateTo(`/cases/${created.id}`)
  } catch (err) {
    formError.value = apiErrorMessage(err, t('cases.new.createFailed'))
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
  <AppShell :title="t('cases.new.title')" :subtitle="t('cases.new.subtitle')" back-to="/cases" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" to="/cases" :aria-label="t('cases.new.cancelCreation')">
        <Icon name="close" class="h-3.5 w-3.5 sm:hidden" />
        <span class="hidden sm:inline">{{ t('common.cancel') }}</span>
      </Button>
      <Button size="sm" :loading="submitting" :aria-label="t('cases.new.create')" @click="submit">
        <Icon v-if="!submitting" name="plus" class="h-3.5 w-3.5 sm:hidden" />
        <span class="hidden sm:inline">{{ submitting ? t('cases.new.creating') : t('cases.new.create') }}</span>
      </Button>
    </template>

    <div class="flex h-full min-h-0 flex-col gap-3 overflow-y-auto p-3 sm:gap-4 sm:p-5 lg:grid lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)] lg:overflow-hidden">
      <div class="flex flex-none flex-col gap-3 sm:gap-4 lg:min-h-0 lg:overflow-y-auto lg:pe-1">
        <InlineAlert v-if="formError" class="!mb-0 flex-none">{{ formError }}</InlineAlert>

        <Card class="flex-none" icon="briefcase" :title="t('cases.new.details')" :subtitle="t('cases.new.detailsSubtitle')">
          <form class="space-y-3.5" @submit.prevent="submit">
            <TextInput v-model="form.reference_no" :label="t('cases.fields.reportNumber')" :placeholder="t('cases.placeholders.reportNumber')" required />
            <TextInput v-model="form.title" :label="t('cases.fields.customerName')" :placeholder="t('cases.placeholders.customerName')" required />
            <TextInput
              v-model="form.property_address"
              :label="t('cases.fields.location')"
              :placeholder="locationLookupState === 'loading' ? t('cases.new.findingLocation') : t('cases.new.moveMapToFill')"
              :hint="locationHint"
              @update:model-value="markLocationManuallyEdited"
            />

            <Select v-model="form.priority" :label="t('cases.fields.priority')">
              <option v-for="priority in CASE_PRIORITIES" :key="priority" :value="priority">
                {{ t(`case.priorities.${priority}`) }}
              </option>
            </Select>

            <div>
              <label for="case-notes" class="mb-1.5 block text-[12px] font-medium text-ink-soft">{{ t('cases.fields.notes') }}</label>
              <textarea
                id="case-notes"
                v-model="form.notes"
                rows="4"
                :placeholder="t('cases.placeholders.notes')"
                class="field h-auto resize-none py-2.5"
              />
            </div>
          </form>
        </Card>

        <Card class="flex-none" icon="user-circle" :title="t('cases.new.whatNext')">
          <ol class="space-y-2.5 text-[12.5px] text-ink-soft">
            <li class="flex gap-2.5">
              <span class="grid h-5 w-5 flex-none place-items-center rounded-full bg-primary-soft text-[11px] font-bold text-primary-strong">{{ number(1) }}</span>
              {{ t('cases.new.nextStep1') }}
            </li>
            <li class="flex gap-2.5">
              <span class="grid h-5 w-5 flex-none place-items-center rounded-full bg-primary-soft text-[11px] font-bold text-primary-strong">{{ number(2) }}</span>
              {{ t('cases.new.nextStep2') }}
            </li>
            <li class="flex gap-2.5">
              <span class="grid h-5 w-5 flex-none place-items-center rounded-full bg-primary-soft text-[11px] font-bold text-primary-strong">{{ number(3) }}</span>
              {{ t('cases.new.nextStep3') }}
            </li>
          </ol>
        </Card>
      </div>

      <Card class="flex-none lg:min-h-0" icon="map-pin" :title="t('cases.fields.locationOnMap')" :subtitle="hasLocation ? t('cases.new.dragPin') : t('cases.new.dropPin')" flush>
        <template #actions>
          <Badge :variant="hasLocation ? 'success' : 'warning'">
            {{ hasLocation ? `${form.lat!.toFixed(5)}, ${form.lng!.toFixed(5)}` : t('cases.new.notSet') }}
          </Badge>
        </template>
        <div class="h-[360px] min-h-[360px] sm:h-[420px] sm:min-h-[420px] lg:h-full">
          <LocationPicker :lat="form.lat" :lng="form.lng" @location-selected="selectLocation" />
        </div>
      </Card>
    </div>
  </AppShell>
</template>
