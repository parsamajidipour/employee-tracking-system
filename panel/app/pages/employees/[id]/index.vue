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

function refreshAll() {
  loadEmployees()
  loadTemplates()
}

onMounted(refreshAll)
</script>

<template>
  <AppShell :title="employee?.name ?? 'Employee'" back-to="/employees">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" @click="refreshAll">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
      <Button size="sm" :to="`/employees/${employeeId}/histories`">Histories</Button>
    </template>

    <InlineAlert v-if="error">{{ error }}</InlineAlert>

    <div v-if="loading" class="space-y-3">
      <Skeleton class="h-16" rounded="md" />
      <Skeleton class="h-16" rounded="md" />
    </div>

    <template v-else-if="employee">
      <div class="mb-5 flex items-end justify-between gap-4">
        <div>
          <p class="text-[13px] text-ink-soft">{{ employee.email ?? '—' }}</p>
          <p class="mt-1 text-[11.5px] text-ink-faint">Select one or more shifts. Tracking is allowed only inside selected times.</p>
        </div>
        <Button :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save shifts' }}</Button>
      </div>

      <div class="max-w-4xl">
        <ShiftPicker v-model="selectedIds" :shifts="templates" />
      </div>
    </template>
  </AppShell>
</template>
