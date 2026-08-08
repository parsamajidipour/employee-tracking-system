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
const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

function toggleShift(id: number) {
  form.shift_template_ids = form.shift_template_ids.includes(id)
    ? form.shift_template_ids.filter((selectedId) => selectedId !== id)
    : [...form.shift_template_ids, id]
}

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
  <AppShell title="Add employee">
    <form @submit.prevent="submit" class="max-w-3xl space-y-4 rounded border border-hairline bg-surface p-4">
      <InlineAlert v-if="error">{{ error }}</InlineAlert>

      <TextInput v-model="form.name" label="Name" required />
      <TextInput v-model="form.phone" label="Phone" />
      <TextInput v-model="form.username" label="Username" required hint="Used to log in on the mobile app — not an email." />
      <TextInput v-model="form.password" type="password" label="Password" required :minlength="8" />

      <fieldset class="space-y-2">
        <legend class="text-sm font-medium text-ink">Shifts</legend>
        <p v-if="loadingShifts" class="text-sm text-ink-faint">Loading…</p>
        <div v-else class="grid gap-2 sm:grid-cols-2">
          <button
            v-for="shift in templates"
            :key="shift.id"
            type="button"
            class="flex items-center gap-3 rounded-control border p-3 text-left transition-colors"
            :class="form.shift_template_ids.includes(shift.id) ? 'border-primary bg-primary-soft' : 'border-hairline hover:border-primary'"
            @click="toggleShift(shift.id)"
          >
            <span class="grid h-5 w-5 flex-none place-items-center rounded-small border text-xs font-bold" :class="form.shift_template_ids.includes(shift.id) ? 'border-primary bg-primary text-white' : 'border-hairline'">{{ form.shift_template_ids.includes(shift.id) ? '✓' : '' }}</span>
            <span>
              <strong class="block text-sm text-ink">{{ shift.name }}</strong>
              <span class="block text-xs text-ink-soft">{{ shift.start_time.slice(0, 5) }} – {{ shift.end_time.slice(0, 5) }} · {{ shift.days_of_week.map(day => dayNames[day]).join(' ') }}</span>
            </span>
          </button>
        </div>
        <p v-if="!loadingShifts && templates.length === 0" class="text-sm text-ink-faint">No shifts exist.</p>
      </fieldset>

      <label class="flex items-center gap-2 text-sm text-ink">
        <input type="checkbox" v-model="form.is_active" class="accent-primary" />
        Active
      </label>

      <div class="flex items-center gap-2 pt-2">
        <Button type="submit" :disabled="submitting">Create employee</Button>
        <Button variant="secondary" to="/employees">Cancel</Button>
      </div>
    </form>
  </AppShell>
</template>
