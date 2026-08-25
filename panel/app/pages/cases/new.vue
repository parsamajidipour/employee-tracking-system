<script setup lang="ts">
import type { NewCasePayload } from '~/composables/useCases'
import { CASE_PRIORITIES, casePriorityLabel } from '~/utils/caseStatus'

const toast = useToast()

const submitting = ref(false)
const formError = ref<string | null>(null)

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

function setLat(value: number) {
  form.lat = value
}

function setLng(value: number) {
  form.lng = value
}

async function submit() {
  if (!canSubmit.value) {
    formError.value = 'Reference, title, and a property location on the map are required.'
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
</script>

<template>
  <AppShell title="New case" subtitle="Create an inspection case" back-to="/cases" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" to="/cases">Cancel</Button>
      <Button size="sm" :loading="submitting" @click="submit">
        {{ submitting ? 'Creating…' : 'Create case' }}
      </Button>
    </template>

    <div class="grid min-h-full grid-cols-1 gap-3 overflow-y-auto p-3 sm:gap-4 sm:p-5 lg:h-full lg:min-h-0 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)] lg:overflow-hidden">
      <div class="flex min-h-fit flex-col gap-3 sm:gap-4 lg:min-h-0 lg:overflow-y-auto lg:pr-1">
        <InlineAlert v-if="formError" class="!mb-0 flex-none">{{ formError }}</InlineAlert>

        <Card class="flex-none" icon="briefcase" title="Case details" subtitle="What is being inspected, and how urgently">
          <form class="space-y-3.5" @submit.prevent="submit">
            <TextInput v-model="form.reference_no" label="Reference no." placeholder="e.g. INS-1042" required />
            <TextInput v-model="form.title" label="Title" placeholder="e.g. Villa valuation" required />
            <TextInput v-model="form.property_address" label="Property address" placeholder="e.g. Al Seeb, Muscat" />

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

      <Card class="min-h-fit lg:min-h-0" icon="map-pin" title="Property location" :subtitle="hasLocation ? 'Drag the pin to fine-tune' : 'Required — click the map to drop the pin'" flush>
        <template #actions>
          <Badge :variant="hasLocation ? 'success' : 'warning'">
            {{ hasLocation ? `${form.lat!.toFixed(5)}, ${form.lng!.toFixed(5)}` : 'Not set' }}
          </Badge>
        </template>
        <div class="h-[360px] min-h-[360px] sm:h-[420px] sm:min-h-[420px] lg:h-full">
          <LocationPicker :lat="form.lat" :lng="form.lng" @update:lat="setLat" @update:lng="setLng" />
        </div>
      </Card>
    </div>
  </AppShell>
</template>
