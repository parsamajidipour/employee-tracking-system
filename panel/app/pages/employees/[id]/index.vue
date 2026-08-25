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

const assignedIds = computed(() => employee.value?.shifts.map((shift) => shift.id) ?? [])
const isDirty = computed(() => {
  const before = [...assignedIds.value].sort().join(',')
  const after = [...selectedIds.value].sort().join(',')
  return before !== after
})

watch(employee, (value) => {
  selectedIds.value = value?.shifts.map((shift) => shift.id) ?? []
}, { immediate: true })

async function save() {
  if (!isDirty.value) return

  saving.value = true
  try {
    await apiFetch(`/api/v1/employees/${employeeId}/shifts`, {
      method: 'PUT',
      body: { shift_template_ids: selectedIds.value },
    })
    toast.success('Working shifts saved.')
    await refreshEmployees()
  } catch (err) {
    toast.error(apiErrorMessage(err, 'Saving shifts failed.'))
  } finally {
    saving.value = false
  }
}

function reset() {
  selectedIds.value = [...assignedIds.value]
}

function refreshAll() {
  loadEmployees()
  loadTemplates()
}

function deviceLabel(): string {
  const device = employee.value?.device
  if (!device) return 'No device paired'
  return device.device_name ?? device.device_identifier
}

onMounted(refreshAll)
</script>

<template>
  <AppShell :title="employee?.name ?? 'Employee'" subtitle="Working schedule" back-to="/employees" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" aria-label="Refresh employee" @click="refreshAll">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
      <Button variant="secondary" size="sm" :to="`/employees/${employeeId}/histories`">
        <Icon name="history" class="h-3.5 w-3.5" />
        <span class="hidden sm:inline">Histories</span>
      </Button>
    </template>

    <div class="flex h-full min-h-0 flex-col gap-4 overflow-y-auto p-4 sm:p-5">
      <InlineAlert v-if="error" class="!mb-0 flex-none">{{ error }}</InlineAlert>

      <div v-if="loading" class="flex-none space-y-3">
        <Skeleton class="h-24" rounded="md" />
        <Skeleton class="h-64" rounded="md" />
      </div>

      <template v-else-if="employee">
        <Card class="flex-none" icon="user-circle" title="Employee" :subtitle="employee.email ?? 'No email on file'">
          <template #actions>
            <Badge :variant="employee.is_active ? 'success' : 'neutral'">
              {{ employee.is_active ? 'Active' : 'Inactive' }}
            </Badge>
          </template>

          <div class="flex flex-wrap items-center gap-4">
            <Avatar :name="employee.name" size="lg" :muted="!employee.is_active" />
            <dl class="grid flex-1 grid-cols-2 gap-x-5 gap-y-3 text-[13px] sm:grid-cols-4">
              <div>
                <dt class="eyebrow mb-1">Phone</dt>
                <dd class="tabular text-ink">{{ employee.phone ?? '—' }}</dd>
              </div>
              <div>
                <dt class="eyebrow mb-1">Device</dt>
                <dd class="truncate" :class="employee.device ? 'text-ink' : 'text-ink-faint'">{{ deviceLabel() }}</dd>
              </div>
              <div>
                <dt class="eyebrow mb-1">Shifts assigned</dt>
                <dd class="tabular text-ink">{{ assignedIds.length }}</dd>
              </div>
              <div>
                <dt class="eyebrow mb-1">Tracked</dt>
                <dd>
                  <Badge :variant="assignedIds.length > 0 && employee.is_active ? 'success' : 'neutral'">
                    {{ assignedIds.length > 0 && employee.is_active ? 'In shift windows' : 'Never' }}
                  </Badge>
                </dd>
              </div>
            </dl>
          </div>
        </Card>

        <InlineAlert v-if="!employee.is_active" variant="warning" class="!mb-0 flex-none">
          This employee is deactivated. Reactivate them from the roster before changing their schedule.
        </InlineAlert>

        <Card
          class="min-h-0 flex-1"
          icon="calendar"
          title="Working shifts"
          :subtitle="`${selectedIds.length} of ${templates.length} selected`"
        >
          <template #actions>
            <Button v-if="isDirty" variant="ghost" size="sm" :disabled="saving" @click="reset">Discard</Button>
            <Button size="sm" :disabled="!isDirty" :loading="saving" @click="save">
              {{ saving ? 'Saving…' : 'Save shifts' }}
            </Button>
          </template>

          <p class="mb-3.5 text-[12.5px] text-ink-soft">
            Location is recorded only inside a selected shift window. Clearing every shift stops tracking this
            employee entirely — no point is stored outside these times.
          </p>
          <ShiftPicker v-model="selectedIds" :shifts="templates" />
        </Card>
      </template>

      <EmptyState v-else icon="users" message="This employee is no longer on the roster." />
    </div>
  </AppShell>
</template>
