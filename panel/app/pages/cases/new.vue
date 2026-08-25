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

function setLat(value: number) {
  form.lat = value
}

function setLng(value: number) {
  form.lng = value
}

async function submit() {
  if (!form.reference_no || !form.title || form.lat === null || form.lng === null) {
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
      lat: form.lat,
      lng: form.lng,
      priority: form.priority,
      notes: form.notes || undefined,
    }
    const created = await createCase(payload)
    toast.success('Case created. Assign it to a surveyor from here.')
    await navigateTo(`/cases/${created.id}`)
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Could not create case — check the fields.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppShell title="New case" subtitle="Create an inspection case" back-to="/cases" full-bleed>
    <template #actions>
      <Button variant="secondary" @click="navigateTo('/cases')">Cancel</Button>
      <Button :loading="submitting" @click="submit">{{ submitting ? 'Creating…' : 'Create case' }}</Button>
    </template>

    <div class="grid h-full grid-cols-1 gap-5 overflow-y-auto p-6 sm:p-7 lg:grid-cols-[minmax(0,420px)_1fr] lg:overflow-hidden">
      <div class="flex min-h-0 flex-col gap-4 lg:overflow-y-auto lg:pr-1">
        <InlineAlert v-if="formError">{{ formError }}</InlineAlert>

        <section class="surface-flat p-5">
          <header class="mb-3.5 flex items-center justify-between">
            <h2>Case details</h2>
          </header>
          <form class="space-y-3.5" @submit.prevent="submit">
            <TextInput v-model="form.reference_no" label="Reference no." placeholder="e.g. INS-1042" required />
            <TextInput v-model="form.title" label="Title" placeholder="e.g. Villa valuation" required />
            <TextInput v-model="form.property_address" label="Property address" placeholder="e.g. Al Seeb, Muscat" />

            <Select v-model="form.priority" label="Priority">
              <option v-for="priority in CASE_PRIORITIES" :key="priority" :value="priority">{{ casePriorityLabel(priority) }}</option>
            </Select>

            <div>
              <label class="mb-1.5 block text-[12px] font-medium text-ink-soft">Notes</label>
              <textarea
                v-model="form.notes"
                rows="4"
                placeholder="Anything a surveyor should know before accepting this case"
                class="field h-auto py-2.5"
              />
            </div>
          </form>
        </section>

        <section class="surface-flat flex items-start gap-3 p-4">
          <span class="grid h-9 w-9 flex-none place-items-center rounded-md bg-primary-soft text-primary-strong">
            <Icon name="user-circle" class="h-4.5 w-4.5" />
          </span>
          <p class="text-[12.5px] text-ink-soft">
            Assigning to a surveyor happens on the case page after it's created — it's not part of this step.
            Every active employee is notified this case exists as soon as it's created.
          </p>
        </section>
      </div>

      <div class="min-h-[420px] lg:h-full">
        <LocationPicker :lat="form.lat" :lng="form.lng" @update:lat="setLat" @update:lng="setLng" />
      </div>
    </div>
  </AppShell>
</template>
