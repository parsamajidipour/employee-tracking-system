<script setup lang="ts">
const route = useRoute()
const employeeId = Number(route.params.id)
const toast = useToast()
const selectedIds = ref<number[]>([])
const saving = ref(false)

const { data: employeesData, loading: employeesLoading, error: employeesError, load: loadEmployees, refresh: refreshEmployees } = useEmployees()
const { data: templatesData, loading: templatesLoading, error: templatesError, load: loadTemplates } = useShiftTemplates()

const employee = computed(() => employeesData.value?.find((item) => item.id === employeeId) ?? null)
const templates = computed(() => templatesData.value ?? [])
const loading = computed(() => employeesLoading.value || templatesLoading.value)
const error = computed(() => (employeesError.value || templatesError.value) ? 'Could not load employee shifts.' : null)

const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

function formatTime(value: string) {
  return value.slice(0, 5)
}

function toggle(id: number) {
  selectedIds.value = selectedIds.value.includes(id)
    ? selectedIds.value.filter((selectedId) => selectedId !== id)
    : [...selectedIds.value, id]
}

watch(employee, (value) => {
  selectedIds.value = value?.shifts.map((shift) => shift.id) ?? []
}, { immediate: true })

async function save() {
  saving.value = true
  try {
    await apiFetch(`/api/v1/employees/${employeeId}/shifts`, {
      method: 'PUT',
      body: { shift_template_ids: selectedIds.value },
    })
    toast.success('Working shifts saved.')
    await refreshEmployees()
  } catch {
    toast.error('Saving shifts failed.')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadEmployees()
  loadTemplates()
})
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
