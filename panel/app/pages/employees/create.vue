<script setup lang="ts">
interface ShiftTemplate {
  id: number
  name: string
  start_time: string
  end_time: string
  days_of_week: number[]
}

const form = reactive({
  name: '',
  phone: '',
  username: '',
  password: '',
  is_active: true,
  shift_template_ids: [] as number[],
})

const templates = ref<ShiftTemplate[]>([])
const loadingShifts = ref(true)
const error = ref<string | null>(null)
const submitting = ref(false)
const toast = useToast()
const { refresh: refreshEmployees } = useEmployees()

onMounted(async () => {
  try {
    templates.value = await apiFetch<ShiftTemplate[]>('/api/v1/shift-templates')
  } catch {
    error.value = 'Could not load shifts.'
  } finally {
    loadingShifts.value = false
  }
})

async function submit() {
  error.value = null
  submitting.value = true
  try {
    await apiFetch('/api/v1/employees', {
      method: 'POST',
      body: {
        name: form.name,
        phone: form.phone || null,
        username: form.username,
        password: form.password,
        is_active: form.is_active,
        shift_template_ids: form.shift_template_ids,
      },
    })
    await refreshEmployees()
    toast.success('Employee created.')
    await navigateTo('/employees')
  } catch {
    error.value = 'Save failed — check the fields (username must be unique, password at least 8 characters).'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppShell title="Add employee" back-to="/employees">
    <form @submit.prevent="submit" class="surface-flat max-w-2xl space-y-4 p-5">
      <InlineAlert v-if="error">{{ error }}</InlineAlert>

      <div class="grid gap-4 sm:grid-cols-2">
        <TextInput v-model="form.name" label="Name" required />
        <TextInput v-model="form.phone" label="Phone" />
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <TextInput v-model="form.username" label="Username" required hint="Used to log in on the mobile app — not an email." />
        <TextInput v-model="form.password" type="password" label="Password" required :minlength="8" />
      </div>

      <fieldset class="space-y-2">
        <legend class="mb-2 text-[13px] font-medium text-ink">Shifts</legend>
        <ShiftPicker v-model="form.shift_template_ids" :shifts="templates" :loading="loadingShifts" />
      </fieldset>

      <label class="flex items-center gap-2 text-[13px] text-ink">
        <input type="checkbox" v-model="form.is_active" class="accent-primary" />
        Active
      </label>

      <div class="flex items-center gap-2 pt-2">
        <Button type="submit" :disabled="submitting">{{ submitting ? 'Creating…' : 'Create employee' }}</Button>
        <Button variant="secondary" to="/employees">Cancel</Button>
      </div>
    </form>
  </AppShell>
</template>
