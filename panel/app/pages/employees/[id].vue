<script setup lang="ts">
interface Team {
  id: number
  name: string
  timezone: string
}

interface Employee {
  id: number
  name: string
  email: string
  team_id: number | null
  team?: Team | null
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

const employee = ref<Employee | null>(null)
const templates = ref<ShiftTemplate[]>([])
const shifts = ref<EmployeeShift[]>([])
const exceptions = ref<ShiftException[]>([])
const error = ref('')
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

    if (employee.value?.team_id) {
      templates.value = await apiFetch<ShiftTemplate[]>(`/api/v1/shift-templates?team_id=${employee.value.team_id}`)
    }

    shifts.value = await apiFetch<EmployeeShift[]>(`/api/v1/employee-shifts?employee_id=${employeeId}`)
    exceptions.value = await apiFetch<ShiftException[]>(`/api/v1/shift-exceptions?employee_id=${employeeId}`)
    error.value = ''
  } catch {
    error.value = 'Not signed in, or failed to load this employee.'
  } finally {
    loading.value = false
  }
}

async function loadResolvedWindow() {
  windowLoading.value = true
  try {
    const result = await apiFetch<{ date: string; window: ResolvedWindow | null }>(
      `/api/v1/employees/${employeeId}/window?date=${windowDate.value}`
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
  error.value = ''
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
    } else {
      await apiFetch(`/api/v1/employee-shifts/${shiftEditingId.value}`, {
        method: 'PUT',
        body: {
          template_id: shiftForm.template_id,
          effective_to: shiftForm.effective_to || null,
          reason: shiftForm.reason || null,
        },
      })
    }
    resetShiftForm()
    await loadAll()
  } catch {
    error.value = 'Saving the schedule row failed — effective_from cannot be in the past.'
  }
}

async function removeShift(shift: EmployeeShift) {
  if (!confirm('Delete this schedule row?')) return
  try {
    await apiFetch(`/api/v1/employee-shifts/${shift.id}`, { method: 'DELETE' })
    await loadAll()
  } catch {
    error.value = 'Delete failed.'
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
  error.value = ''
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
    } else {
      await apiFetch(`/api/v1/shift-exceptions/${exceptionEditingId.value}`, { method: 'PUT', body })
    }
    resetExceptionForm()
    await loadAll()
  } catch {
    error.value = 'Saving the exception failed — overtime/early_end need both times.'
  }
}

async function removeException(exception: ShiftException) {
  if (!confirm('Delete this exception?')) return
  try {
    await apiFetch(`/api/v1/shift-exceptions/${exception.id}`, { method: 'DELETE' })
    await loadAll()
  } catch {
    error.value = 'Delete failed.'
  }
}

onMounted(async () => {
  await loadAll()
  resetShiftForm()
  await loadResolvedWindow()
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-8">
    <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>
    <div v-if="loading" class="text-slate-500">Loading…</div>

    <template v-else-if="employee">
      <h1 class="mb-1 text-xl font-semibold text-slate-900">{{ employee.name }}</h1>
      <p class="mb-6 text-sm text-slate-500">{{ employee.email }} — {{ employee.team?.name ?? 'no team' }}</p>

      <div class="mb-8 grid max-w-5xl grid-cols-2 gap-6">
        <!-- Resolved window: what the resolver actually produces -->
        <div class="rounded border border-slate-200 bg-white p-4">
          <h2 class="mb-2 text-sm font-semibold text-slate-900">Resolved window</h2>
          <div class="mb-2 flex items-center gap-2">
            <input v-model="windowDate" type="date" class="rounded border border-slate-300 px-2 py-1 text-sm" @change="loadResolvedWindow" />
          </div>
          <p v-if="windowLoading" class="text-sm text-slate-500">Resolving…</p>
          <p v-else-if="resolvedWindow" class="text-sm text-slate-700">
            {{ new Date(resolvedWindow.start).toLocaleString() }} –
            {{ new Date(resolvedWindow.end).toLocaleString() }}
            <span class="text-slate-400">({{ resolvedWindow.source }}, graced)</span>
          </p>
          <p v-else class="text-sm text-slate-500">No window on this date — denied.</p>
        </div>

        <!-- Raw employee_shifts rows -->
        <div class="rounded border border-slate-200 bg-white p-4">
          <h2 class="mb-2 text-sm font-semibold text-slate-900">Schedule overrides (raw rows)</h2>
          <table class="mb-3 w-full text-left text-xs">
            <thead>
              <tr class="border-b border-slate-300">
                <th class="py-1 pr-2">Template</th>
                <th class="py-1 pr-2">From</th>
                <th class="py-1 pr-2">To</th>
                <th class="py-1"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="shift in shifts" :key="shift.id" class="border-b border-slate-200">
                <td class="py-1 pr-2">{{ shift.template?.name }}</td>
                <td class="py-1 pr-2">{{ shift.effective_from }}</td>
                <td class="py-1 pr-2">{{ shift.effective_to ?? '—' }}</td>
                <td class="py-1">
                  <button @click="editShift(shift)" class="mr-2 text-blue-600 hover:underline">Edit</button>
                  <button @click="removeShift(shift)" class="text-red-600 hover:underline">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>

          <form @submit.prevent="submitShift" class="space-y-2 border-t border-slate-100 pt-2">
            <div class="flex gap-2">
              <select v-model.number="shiftForm.template_id" required class="flex-1 rounded border border-slate-300 px-2 py-1 text-sm">
                <option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }}</option>
              </select>
            </div>
            <div class="flex gap-2">
              <input
                v-if="shiftEditingId === null"
                v-model="shiftForm.effective_from"
                type="datetime-local"
                required
                class="flex-1 rounded border border-slate-300 px-2 py-1 text-sm"
                placeholder="Effective from"
              />
              <input
                v-model="shiftForm.effective_to"
                type="datetime-local"
                class="flex-1 rounded border border-slate-300 px-2 py-1 text-sm"
                placeholder="Effective to (optional)"
              />
            </div>
            <input v-model="shiftForm.reason" placeholder="Reason (optional)" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
            <div>
              <button type="submit" class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white">
                {{ shiftEditingId === null ? 'Add override' : 'Save' }}
              </button>
              <button v-if="shiftEditingId !== null" type="button" @click="resetShiftForm" class="ml-2 rounded border border-slate-300 px-3 py-1.5 text-sm">
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Raw shift_exceptions rows -->
      <div class="max-w-2xl rounded border border-slate-200 bg-white p-4">
        <h2 class="mb-2 text-sm font-semibold text-slate-900">Exceptions (raw rows)</h2>
        <table class="mb-3 w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-300">
              <th class="py-1 pr-2">Date</th>
              <th class="py-1 pr-2">Type</th>
              <th class="py-1 pr-2">Start</th>
              <th class="py-1 pr-2">End</th>
              <th class="py-1 pr-2">Note</th>
              <th class="py-1"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="exception in exceptions" :key="exception.id" class="border-b border-slate-200">
              <td class="py-1 pr-2">{{ exception.date }}</td>
              <td class="py-1 pr-2">{{ exception.type }}</td>
              <td class="py-1 pr-2">{{ exception.start_at ?? '—' }}</td>
              <td class="py-1 pr-2">{{ exception.end_at ?? '—' }}</td>
              <td class="py-1 pr-2">{{ exception.note ?? '—' }}</td>
              <td class="py-1">
                <button @click="editException(exception)" class="mr-2 text-blue-600 hover:underline">Edit</button>
                <button @click="removeException(exception)" class="text-red-600 hover:underline">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>

        <form @submit.prevent="submitException" class="space-y-2 border-t border-slate-100 pt-2">
          <div class="flex gap-2">
            <input v-model="exceptionForm.date" type="date" required class="rounded border border-slate-300 px-2 py-1 text-sm" />
            <select v-model="exceptionForm.type" class="rounded border border-slate-300 px-2 py-1 text-sm">
              <option value="leave">leave</option>
              <option value="holiday">holiday</option>
              <option value="overtime">overtime</option>
              <option value="early_end">early_end</option>
            </select>
          </div>
          <div v-if="exceptionForm.type === 'overtime' || exceptionForm.type === 'early_end'" class="flex gap-2">
            <input v-model="exceptionForm.start_at" type="time" required class="rounded border border-slate-300 px-2 py-1 text-sm" />
            <input v-model="exceptionForm.end_at" type="time" required class="rounded border border-slate-300 px-2 py-1 text-sm" />
          </div>
          <input v-model="exceptionForm.note" placeholder="Note (optional)" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
          <input v-model="exceptionForm.reason" placeholder="Reason (optional)" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
          <div>
            <button type="submit" class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white">
              {{ exceptionEditingId === null ? 'Add exception' : 'Save' }}
            </button>
            <button v-if="exceptionEditingId !== null" type="button" @click="resetExceptionForm" class="ml-2 rounded border border-slate-300 px-3 py-1.5 text-sm">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </template>

    <p v-else class="text-slate-500">Employee not found.</p>
  </div>
</template>
