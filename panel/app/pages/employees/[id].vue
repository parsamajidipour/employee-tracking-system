<script setup lang="ts">
interface Employee {
  id: number
  name: string
  email: string | null
  username: string | null
}

interface ShiftTemplate {
  id: number
  team_id: number
  name: string
}

interface EmployeeShift {
  id: number
  employee_id: number
  template_id: number
  template?: ShiftTemplate
  effective_from: string
  effective_to: string | null
}

interface ShiftException {
  id: number
  employee_id: number
  date: string
  type: 'leave' | 'holiday' | 'overtime' | 'early_end'
  start_at: string | null
  end_at: string | null
  note: string | null
}

interface ResolvedWindow {
  start: string
  end: string
  source: string
}

const route = useRoute()
const employeeId = Number(route.params.id)
const { confirm } = useConfirm()
const toast = useToast()

const employee = ref<Employee | null>(null)
const templates = ref<ShiftTemplate[]>([])
const shifts = ref<EmployeeShift[]>([])
const exceptions = ref<ShiftException[]>([])
const error = ref<string | null>(null)
const loading = ref(true)

const windowDate = ref(new Date().toISOString().slice(0, 10))
const resolvedWindow = ref<ResolvedWindow | null>(null)
const windowLoading = ref(false)

const shiftEditingId = ref<number | null>(null)
const shiftForm = reactive({
  template_id: null as number | null,
  effective_from: '',
  effective_to: '',
  reason: '',
})

const exceptionEditingId = ref<number | null>(null)
const exceptionForm = reactive({
  date: '',
  type: 'leave' as ShiftException['type'],
  start_at: '',
  end_at: '',
  note: '',
  reason: '',
})

async function loadAll() {
  loading.value = true
  try {
    const employees = await apiFetch<Employee[]>('/api/v1/employees')
    employee.value = employees.find((e) => e.id === employeeId) ?? null

    // No team filter — this deployment has exactly one team (see
    // DECISIONS.md), so every template already belongs to it.
    templates.value = await apiFetch<ShiftTemplate[]>('/api/v1/shift-templates')

    shifts.value = await apiFetch<EmployeeShift[]>(`/api/v1/employee-shifts?employee_id=${employeeId}`)
    exceptions.value = await apiFetch<ShiftException[]>(`/api/v1/shift-exceptions?employee_id=${employeeId}`)
    error.value = null
  } catch {
    error.value = 'Could not load this employee. Sign in and try again.'
  } finally {
    loading.value = false
  }
}

async function loadResolvedWindow() {
  windowLoading.value = true
  try {
    const result = await apiFetch<{ date: string; window: ResolvedWindow | null }>(
      `/api/v1/employees/${employeeId}/window?date=${windowDate.value}`,
    )
    resolvedWindow.value = result.window
  } catch {
    resolvedWindow.value = null
  } finally {
    windowLoading.value = false
  }
}

function resetShiftForm() {
  shiftEditingId.value = null
  shiftForm.template_id = templates.value[0]?.id ?? null
  shiftForm.effective_from = ''
  shiftForm.effective_to = ''
  shiftForm.reason = ''
}

function editShift(shift: EmployeeShift) {
  shiftEditingId.value = shift.id
  shiftForm.template_id = shift.template_id
  shiftForm.effective_from = shift.effective_from
  shiftForm.effective_to = shift.effective_to ?? ''
  shiftForm.reason = ''
}

async function submitShift() {
  try {
    if (shiftEditingId.value === null) {
      await apiFetch('/api/v1/employee-shifts', {
        method: 'POST',
        body: {
          employee_id: employeeId,
          template_id: shiftForm.template_id,
          effective_from: shiftForm.effective_from,
          effective_to: shiftForm.effective_to || null,
          reason: shiftForm.reason || null,
        },
      })
      toast.success('Schedule override added.')
    } else {
      await apiFetch(`/api/v1/employee-shifts/${shiftEditingId.value}`, {
        method: 'PUT',
        body: {
          template_id: shiftForm.template_id,
          effective_to: shiftForm.effective_to || null,
          reason: shiftForm.reason || null,
        },
      })
      toast.success('Schedule override saved.')
    }
    resetShiftForm()
    await loadAll()
  } catch {
    toast.error('Saving the schedule row failed — effective_from cannot be in the past.')
  }
}

async function removeShift(shift: EmployeeShift) {
  if (!(await confirm('Delete this schedule row?', { variant: 'danger', title: 'Delete schedule row' }))) return
  try {
    await apiFetch(`/api/v1/employee-shifts/${shift.id}`, { method: 'DELETE' })
    toast.success('Schedule row deleted.')
    await loadAll()
  } catch {
    toast.error('Delete failed.')
  }
}

function resetExceptionForm() {
  exceptionEditingId.value = null
  exceptionForm.date = ''
  exceptionForm.type = 'leave'
  exceptionForm.start_at = ''
  exceptionForm.end_at = ''
  exceptionForm.note = ''
  exceptionForm.reason = ''
}

function editException(exception: ShiftException) {
  exceptionEditingId.value = exception.id
  exceptionForm.date = exception.date
  exceptionForm.type = exception.type
  exceptionForm.start_at = exception.start_at?.slice(0, 5) ?? ''
  exceptionForm.end_at = exception.end_at?.slice(0, 5) ?? ''
  exceptionForm.note = exception.note ?? ''
  exceptionForm.reason = ''
}

async function submitException() {
  const body = {
    date: exceptionForm.date,
    type: exceptionForm.type,
    start_at: exceptionForm.start_at || null,
    end_at: exceptionForm.end_at || null,
    note: exceptionForm.note || null,
    reason: exceptionForm.reason || null,
  }
  try {
    if (exceptionEditingId.value === null) {
      await apiFetch('/api/v1/shift-exceptions', { method: 'POST', body: { employee_id: employeeId, ...body } })
      toast.success('Exception added.')
    } else {
      await apiFetch(`/api/v1/shift-exceptions/${exceptionEditingId.value}`, { method: 'PUT', body })
      toast.success('Exception saved.')
    }
    resetExceptionForm()
    await loadAll()
  } catch {
    toast.error('Saving the exception failed — overtime/early_end need both times.')
  }
}

async function removeException(exception: ShiftException) {
  if (!(await confirm('Delete this exception?', { variant: 'danger', title: 'Delete exception' }))) return
  try {
    await apiFetch(`/api/v1/shift-exceptions/${exception.id}`, { method: 'DELETE' })
    toast.success('Exception deleted.')
    await loadAll()
  } catch {
    toast.error('Delete failed.')
  }
}

onMounted(async () => {
  await loadAll()
  resetShiftForm()
  await loadResolvedWindow()
})
</script>

<template>
  <AppShell :title="employee?.name ?? 'Employee'">
    <InlineAlert v-if="error">{{ error }}</InlineAlert>
    <div v-if="loading" class="text-sm text-slate-400">Loading…</div>

    <template v-else-if="employee">
      <p class="mb-6 text-sm text-slate-500">{{ employee.username ?? employee.email ?? '—' }}</p>

      <div class="mb-8 grid max-w-5xl grid-cols-2 gap-6">
        <!-- Resolved window: what the resolver actually produces -->
        <div class="rounded border border-slate-200 bg-white p-4">
          <h2 class="mb-2 text-sm font-semibold text-slate-900">Resolved window</h2>
          <div class="mb-2">
            <TextInput v-model="windowDate" type="date" @change="loadResolvedWindow" />
          </div>
          <p v-if="windowLoading" class="text-sm text-slate-500">Resolving…</p>
          <p v-else-if="resolvedWindow" class="tabular-nums text-sm text-slate-700">
            {{ new Date(resolvedWindow.start).toLocaleString() }} –
            {{ new Date(resolvedWindow.end).toLocaleString() }}
            <span class="text-slate-400">({{ resolvedWindow.source }}, graced)</span>
          </p>
          <p v-else class="text-sm text-slate-500">No window on this date — denied.</p>
        </div>

        <!-- Raw employee_shifts rows -->
        <div class="rounded border border-slate-200 bg-white p-4">
          <h2 class="mb-2 text-sm font-semibold text-slate-900">Schedule overrides (raw rows)</h2>
          <Table
            :headers="['Template', 'From', 'To', '']"
            :is-empty="shifts.length === 0"
            empty-message="No overrides — this employee follows the team template."
            class="mb-3"
          >
            <tr v-for="shift in shifts" :key="shift.id" class="text-xs text-slate-700">
              <td class="px-4 py-2.5">{{ shift.template?.name }}</td>
              <td class="px-4 py-2.5">{{ shift.effective_from }}</td>
              <td class="px-4 py-2.5">{{ shift.effective_to ?? '—' }}</td>
              <td class="px-4 py-2.5 text-right">
                <Button size="sm" variant="secondary" @click="editShift(shift)">Edit</Button>
                <Button size="sm" variant="danger" class="ml-2" @click="removeShift(shift)">Delete</Button>
              </td>
            </tr>
          </Table>

          <form @submit.prevent="submitShift" class="space-y-2 border-t border-slate-100 pt-3">
            <Select v-model.number="shiftForm.template_id" required>
              <option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }}</option>
            </Select>
            <div class="flex gap-2">
              <TextInput
                v-if="shiftEditingId === null"
                v-model="shiftForm.effective_from"
                type="datetime-local"
                required
                label="Effective from"
                class="flex-1"
              />
              <TextInput v-model="shiftForm.effective_to" type="datetime-local" label="Effective to (optional)" class="flex-1" />
            </div>
            <TextInput v-model="shiftForm.reason" placeholder="Reason (optional)" />
            <div class="flex items-center gap-2">
              <Button type="submit">{{ shiftEditingId === null ? 'Add override' : 'Save' }}</Button>
              <Button v-if="shiftEditingId !== null" type="button" variant="secondary" @click="resetShiftForm">Cancel</Button>
            </div>
          </form>
        </div>
      </div>

      <!-- Raw shift_exceptions rows -->
      <div class="max-w-2xl rounded border border-slate-200 bg-white p-4">
        <h2 class="mb-2 text-sm font-semibold text-slate-900">Exceptions (raw rows)</h2>
        <Table
          :headers="['Date', 'Type', 'Start', 'End', 'Note', '']"
          :is-empty="exceptions.length === 0"
          empty-message="No exceptions recorded."
          class="mb-3"
        >
          <tr v-for="exception in exceptions" :key="exception.id" class="text-xs text-slate-700">
            <td class="px-4 py-2.5">{{ exception.date }}</td>
            <td class="px-4 py-2.5">{{ exception.type }}</td>
            <td class="px-4 py-2.5">{{ exception.start_at ?? '—' }}</td>
            <td class="px-4 py-2.5">{{ exception.end_at ?? '—' }}</td>
            <td class="px-4 py-2.5">{{ exception.note ?? '—' }}</td>
            <td class="px-4 py-2.5 text-right">
              <Button size="sm" variant="secondary" @click="editException(exception)">Edit</Button>
              <Button size="sm" variant="danger" class="ml-2" @click="removeException(exception)">Delete</Button>
            </td>
          </tr>
        </Table>

        <form @submit.prevent="submitException" class="space-y-2 border-t border-slate-100 pt-3">
          <div class="flex gap-2">
            <TextInput v-model="exceptionForm.date" type="date" required class="flex-1" />
            <Select v-model="exceptionForm.type" class="flex-1">
              <option value="leave">leave</option>
              <option value="holiday">holiday</option>
              <option value="overtime">overtime</option>
              <option value="early_end">early_end</option>
            </Select>
          </div>
          <div v-if="exceptionForm.type === 'overtime' || exceptionForm.type === 'early_end'" class="flex gap-2">
            <TextInput v-model="exceptionForm.start_at" type="time" required class="flex-1" />
            <TextInput v-model="exceptionForm.end_at" type="time" required class="flex-1" />
          </div>
          <TextInput v-model="exceptionForm.note" placeholder="Note (optional)" />
          <TextInput v-model="exceptionForm.reason" placeholder="Reason (optional)" />
          <div class="flex items-center gap-2">
            <Button type="submit">{{ exceptionEditingId === null ? 'Add exception' : 'Save' }}</Button>
            <Button v-if="exceptionEditingId !== null" type="button" variant="secondary" @click="resetExceptionForm">Cancel</Button>
          </div>
        </form>
      </div>
    </template>

    <p v-else class="text-sm text-slate-500">Employee not found.</p>
  </AppShell>
</template>
