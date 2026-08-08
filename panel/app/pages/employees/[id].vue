<script setup lang="ts">
interface ShiftTemplate {
  id: number
  name: string
  start_time: string
  end_time: string
  days_of_week: number[]
}

interface Employee {
  id: number
  name: string
  email: string | null
  username: string | null
  shifts: ShiftTemplate[]
}

const route = useRoute()
const employeeId = Number(route.params.id)
const toast = useToast()
const employee = ref<Employee | null>(null)
const templates = ref<ShiftTemplate[]>([])
const selectedIds = ref<number[]>([])
const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)

const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

function formatTime(value: string) {
  return value.slice(0, 5)
}

function toggle(id: number) {
  selectedIds.value = selectedIds.value.includes(id)
    ? selectedIds.value.filter((selectedId) => selectedId !== id)
    : [...selectedIds.value, id]
}

async function load() {
  loading.value = true
  try {
    const [employees, shiftTemplates] = await Promise.all([
      apiFetch<Employee[]>('/api/v1/employees'),
      apiFetch<ShiftTemplate[]>('/api/v1/shift-templates'),
    ])
    employee.value = employees.find((item) => item.id === employeeId) ?? null
    templates.value = shiftTemplates
    selectedIds.value = employee.value?.shifts.map((shift) => shift.id) ?? []
    error.value = null
  } catch {
    error.value = 'Could not load employee shifts.'
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    await apiFetch(`/api/v1/employees/${employeeId}/shifts`, {
      method: 'PUT',
      body: { shift_template_ids: selectedIds.value },
    })
    toast.success('Working shifts saved.')
    await load()
  } catch {
    toast.error('Saving shifts failed.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <AppShell :title="employee?.name ?? 'Employee'">
    <InlineAlert v-if="error">{{ error }}</InlineAlert>
    <p v-if="loading" class="text-sm text-ink-faint">Loading…</p>

    <template v-else-if="employee">
      <div class="mb-6 flex items-end justify-between gap-4">
        <div>
          <p class="text-sm text-ink-soft">{{ employee.username ?? employee.email ?? '—' }}</p>
          <p class="mt-1 text-xs text-ink-faint">Select one or more shifts. Tracking is allowed only inside selected times.</p>
        </div>
        <Button :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save shifts' }}</Button>
      </div>

      <div class="grid max-w-4xl gap-3 sm:grid-cols-2">
        <button
          v-for="shift in templates"
          :key="shift.id"
          type="button"
          class="flex min-h-24 items-center gap-4 rounded-control border bg-surface p-4 text-left transition-colors"
          :class="selectedIds.includes(shift.id) ? 'border-primary bg-primary-soft' : 'border-hairline hover:border-primary'"
          @click="toggle(shift.id)"
        >
          <span
            class="grid h-6 w-6 flex-none place-items-center rounded-small border text-sm font-bold"
            :class="selectedIds.includes(shift.id) ? 'border-primary bg-primary text-white' : 'border-hairline'"
          >{{ selectedIds.includes(shift.id) ? '✓' : '' }}</span>
          <span class="min-w-0 flex-1">
            <strong class="block text-sm text-ink">{{ shift.name }}</strong>
            <span class="mt-1 block text-sm tabular-nums text-ink-soft">{{ formatTime(shift.start_time) }} – {{ formatTime(shift.end_time) }}</span>
            <span class="mt-1 block text-xs text-ink-faint">{{ shift.days_of_week.map((day) => dayNames[day]).join(' · ') }}</span>
          </span>
        </button>
      </div>

      <p v-if="templates.length === 0" class="rounded-control border border-hairline bg-surface p-5 text-sm text-ink-soft">
        No time shifts exist. Create a shift first.
      </p>
    </template>
  </AppShell>
</template>
