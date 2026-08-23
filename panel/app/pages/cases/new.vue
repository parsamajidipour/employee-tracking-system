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
  <AppShell title="New case" back-to="/cases">
    <template #actions>
      <Button variant="secondary" @click="navigateTo('/cases')">Cancel</Button>
      <Button :loading="submitting" @click="submit">{{ submitting ? 'Creating…' : 'Create case' }}</Button>
    </template>

    <InlineAlert v-if="formError" class="mb-4">{{ formError }}</InlineAlert>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,380px)_1fr]">
      <form class="surface-flat space-y-3.5 p-5" @submit.prevent="submit">
        <TextInput v-model="form.reference_no" label="Reference no." required />
        <TextInput v-model="form.title" label="Title" required />
        <TextInput v-model="form.property_address" label="Property address" />

        <Select v-model="form.priority" label="Priority">
          <option v-for="priority in CASE_PRIORITIES" :key="priority" :value="priority">{{ casePriorityLabel(priority) }}</option>
        </Select>

        <div>
          <label class="mb-1.5 block text-[12px] font-medium text-ink-soft">Notes</label>
          <textarea v-model="form.notes" rows="3" class="field h-auto py-2.5" />
        </div>

        <p class="text-[12px] text-ink-faint">
          Assigning to a surveyor happens on the case page after it's created — it's not part of this step.
        </p>
      </form>

      <div class="h-[420px] lg:h-auto lg:min-h-[560px]">
        <LocationPicker :lat="form.lat" :lng="form.lng" @update:lat="setLat" @update:lng="setLng" />
      </div>
    </div>
  </AppShell>
</template>
