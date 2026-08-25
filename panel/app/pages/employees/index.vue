<script setup lang="ts">
import type { Employee } from '~/composables/useEmployees'
import type { WorkloadActivity, WorkloadDetail } from '~/composables/useWorkload'

const { data: employeesData, loading, error: cacheError, load, refresh } = useEmployees()
const { data: workloadData, loading: workloadLoading, load: loadWorkload } = useWorkloadList()
const allEmployees = computed(() => employeesData.value ?? [])
const error = computed(() => (cacheError.value ? 'Could not load employees. Sign in and try again.' : null))

const { confirm } = useConfirm()
const toast = useToast()
const workloadByEmployee = computed(() => new Map(workloadData.value.map(row => [row.employee_id, row])))
const { positions } = usePositions()
const positionByEmployee = computed(() => new Map(positions.value.map(position => [position.employee_id, position])))
const isLoading = computed(() => loading.value || workloadLoading.value)

useCaseStream(() => loadWorkload())

const search = ref('')
const statusFilter = ref<'all' | 'active' | 'inactive'>('all')
const coverageFilter = ref<'all' | 'scheduled' | 'unscheduled'>('all')

const employees = computed(() => {
  const term = search.value.trim().toLowerCase()

  return allEmployees.value.filter((employee) => {
    if (statusFilter.value === 'active' && !employee.is_active) return false
    if (statusFilter.value === 'inactive' && employee.is_active) return false
    if (coverageFilter.value === 'scheduled' && employee.shifts.length === 0) return false
    if (coverageFilter.value === 'unscheduled' && employee.shifts.length > 0) return false
    if (!term) return true

    return [employee.name, employee.phone ?? '', employee.email ?? ''].some((field) =>
      field.toLowerCase().includes(term),
    )
  })
})

const counts = computed(() => ({
  total: allEmployees.value.length,
  activeCases: workloadData.value.reduce((sum, row) => sum + row.summary.active_cases, 0),
  pending: workloadData.value.reduce((sum, row) => sum + row.summary.pending, 0),
  overdue: workloadData.value.reduce((sum, row) => sum + row.summary.overdue, 0),
}))

function workloadPercent(employeeId: number): number {
  const activeCases = workloadByEmployee.value.get(employeeId)?.summary.active_cases ?? 0
  return Math.min(100, Math.round((activeCases / 6) * 100))
}

function activityOf(employee: Employee): { label: string, variant: 'success' | 'warning' | 'danger' | 'neutral' } {
  if (!employee.is_active) return { label: 'Inactive', variant: 'neutral' }
  const summary = workloadByEmployee.value.get(employee.id)?.summary
  if ((summary?.in_progress ?? 0) > 0) return { label: 'Inspecting', variant: 'success' }
  if ((summary?.overdue ?? 0) > 0) return { label: 'Overdue work', variant: 'danger' }
  if ((summary?.pending ?? 0) > 0) return { label: 'Awaiting response', variant: 'warning' }
  if ((summary?.scheduled ?? 0) > 0) return { label: 'Scheduled', variant: 'success' }
  return { label: 'Available', variant: 'neutral' }
}

function locationOf(employee: Employee): string {
  const position = positionByEmployee.value.get(employee.id)
  if (!position) return employee.is_active ? 'Off shift / no live location' : 'Location unavailable'
  return `${position.lat.toFixed(4)}, ${position.lng.toFixed(4)} · ${position.connection_status}`
}

function todayLocalDate(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

const activityDrawerOpen = ref(false)
const activityEmployeeId = ref<number | null>(null)
const activityEmployeeName = ref('')
const activityDate = ref(todayLocalDate())
const activityDetail = ref<WorkloadDetail | null>(null)
const activityLoading = ref(false)
const activityError = ref<string | null>(null)

function minutesLabel(minutes: number): string {
  const hours = Math.floor(minutes / 60)
  const remainder = Math.round(minutes % 60)
  return hours === 0 ? `${remainder}m` : `${hours}h ${remainder}m`
}

function activitySegments(activity: WorkloadActivity) {
  const total = activity.inspection_minutes + activity.travel_minutes + activity.idle_minutes
  if (total <= 0) return { inspection: 0, travel: 0, idle: 100 }
  return {
    inspection: (activity.inspection_minutes / total) * 100,
    travel: (activity.travel_minutes / total) * 100,
    idle: (activity.idle_minutes / total) * 100,
  }
}

function utilizationPercent(activity: WorkloadActivity): number {
  const total = activity.inspection_minutes + activity.travel_minutes + activity.idle_minutes
  if (total <= 0) return 0
  return Math.round(((activity.inspection_minutes + activity.travel_minutes) / total) * 100)
}

function activityRingStyle(activity: WorkloadActivity) {
  const segments = activitySegments(activity)
  const inspectionEnd = segments.inspection
  const travelEnd = inspectionEnd + segments.travel
  return {
    background: `conic-gradient(var(--primary) 0% ${inspectionEnd}%, var(--warning) ${inspectionEnd}% ${travelEnd}%, var(--neutral-soft) ${travelEnd}% 100%)`,
  }
}

async function loadActivityDetail() {
  if (activityEmployeeId.value === null) return
  activityLoading.value = true
  activityError.value = null
  try {
    activityDetail.value = await fetchWorkloadDetail(activityEmployeeId.value, activityDate.value)
  } catch (err) {
    activityError.value = apiErrorMessage(err, 'Could not load activity for this day.')
  } finally {
    activityLoading.value = false
  }
}

function openActivity(employee: Employee) {
  activityEmployeeId.value = employee.id
  activityEmployeeName.value = employee.name
  activityDate.value = todayLocalDate()
  activityDetail.value = null
  activityDrawerOpen.value = true
  loadActivityDetail()
}

watch(activityDate, () => {
  if (activityDrawerOpen.value) loadActivityDetail()
})

async function refreshAll() {
  await Promise.all([refresh(), loadWorkload()])
}

const isFiltered = computed(
  () => search.value.trim() !== '' || statusFilter.value !== 'all' || coverageFilter.value !== 'all',
)

function clearFilters() {
  search.value = ''
  statusFilter.value = 'all'
  coverageFilter.value = 'all'
}

const passwordModalOpen = ref(false)
const passwordTarget = ref<Employee | null>(null)
const passwordSaving = ref(false)
const passwordError = ref<string | null>(null)
const newPassword = ref('')
const confirmPassword = ref('')

const editModalOpen = ref(false)
const editTarget = ref<Employee | null>(null)
const editSaving = ref(false)
const editError = ref<string | null>(null)
const editForm = reactive({ name: '', phone: '', email: '' })

function openEdit(employee: Employee) {
  editTarget.value = employee
  editForm.name = employee.name
  editForm.phone = employee.phone ?? ''
  editForm.email = employee.email ?? ''
  editError.value = null
  editModalOpen.value = true
}

async function submitEdit() {
  if (!editTarget.value) return

  editSaving.value = true
  editError.value = null
  try {
    await apiFetch(`/api/v1/employees/${editTarget.value.id}`, {
      method: 'PUT',
      body: { name: editForm.name, phone: editForm.phone, email: editForm.email },
    })
    toast.success('Employee updated.')
    editModalOpen.value = false
    await refresh()
  } catch (err) {
    editError.value = apiErrorMessage(err, 'Update failed — check the fields (phone and email must be unique).')
  } finally {
    editSaving.value = false
  }
}

function openChangePassword(employee: Employee) {
  passwordTarget.value = employee
  newPassword.value = ''
  confirmPassword.value = ''
  passwordError.value = null
  passwordModalOpen.value = true
}

async function submitChangePassword() {
  if (!passwordTarget.value) return

  if (newPassword.value.length < 8) {
    passwordError.value = 'Password must be at least 8 characters.'
    return
  }
  if (newPassword.value !== confirmPassword.value) {
    passwordError.value = 'Passwords do not match.'
    return
  }

  passwordSaving.value = true
  passwordError.value = null
  try {
    await apiFetch(`/api/v1/employees/${passwordTarget.value.id}/password`, {
      method: 'PUT',
      body: { password: newPassword.value, password_confirmation: confirmPassword.value },
    })
    toast.success('Password changed.')
    passwordModalOpen.value = false
  } catch (err) {
    passwordError.value = apiErrorMessage(err, 'Password change failed.')
  } finally {
    passwordSaving.value = false
  }
}

async function toggleActive(employee: Employee) {
  const next = !employee.is_active
  const confirmed = await confirm(
    next
      ? `Activate ${employee.name}? They will be able to sign in and be assigned cases again.`
      : `Deactivate ${employee.name}? Their tokens are revoked immediately and they can no longer be assigned cases.`,
    {
      title: next ? 'Activate employee' : 'Deactivate employee',
      variant: next ? 'default' : 'danger',
    },
  )
  if (!confirmed) return
  try {
    await apiFetch(`/api/v1/employees/${employee.id}/active`, { method: 'PUT', body: { is_active: next } })
    toast.success(next ? 'Employee activated.' : 'Employee deactivated.')
    await refresh()
  } catch (err) {
    toast.error(apiErrorMessage(err, 'Update failed.'))
  }
}

async function revokeDevice(employee: Employee) {
  const confirmed = await confirm(`Revoke ${employee.name}'s device? They will need to log in again on a new phone.`, {
    title: 'Revoke device',
    variant: 'danger',
  })
  if (!confirmed) return
  try {
    await apiFetch(`/api/v1/employees/${employee.id}/device`, { method: 'DELETE' })
    toast.success('Device revoked.')
    await refresh()
  } catch (err) {
    toast.error(apiErrorMessage(err, 'Revoke failed.'))
  }
}

async function removeEmployee(employee: Employee) {
  const confirmed = await confirm(
    `Delete ${employee.name}? They will be removed from the roster, their device access revoked, and their shift assignment cleared.`,
    { title: 'Delete employee', variant: 'danger' },
  )
  if (!confirmed) return
  try {
    await apiFetch(`/api/v1/employees/${employee.id}`, { method: 'DELETE' })
    toast.success('Employee deleted.')
    await refresh()
  } catch (err) {
    toast.error(apiErrorMessage(err, 'Delete failed.'))
  }
}

onMounted(() => Promise.all([load(), loadWorkload()]))
</script>

<template>
  <AppShell title="Employees" subtitle="Availability, workload, schedules and access in one place" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="isLoading" aria-label="Refresh employees" @click="refreshAll">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="isLoading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
      <Button size="sm" to="/employees/create">
        <Icon name="plus" class="h-3.5 w-3.5" />
        <span class="hidden sm:inline">Add employee</span>
      </Button>
    </template>

    <div class="flex h-full min-h-0 flex-col gap-3 overflow-y-auto p-3 sm:gap-4 sm:p-5 lg:overflow-hidden">
      <div class="grid flex-none grid-cols-2 gap-2.5 lg:grid-cols-4">
        <StatCard icon="users" label="On the roster" :value="String(counts.total)" accent="primary" />
        <StatCard icon="briefcase" label="Active cases" :value="String(counts.activeCases)" accent="neutral" />
        <StatCard icon="clock" label="Awaiting acceptance" :value="String(counts.pending)" :accent="counts.pending > 0 ? 'warning' : 'neutral'" />
        <StatCard icon="alert-triangle" label="Overdue" :value="String(counts.overdue)" :accent="counts.overdue > 0 ? 'danger' : 'neutral'" />
      </div>

      <Card
        class="flex-none lg:min-h-0 lg:flex-1"
        icon="users"
        title="Workforce & workload"
        :subtitle="`${employees.length} shown of ${counts.total} · live operational capacity`"
        flush
      >
        <div class="flex min-h-0 flex-col lg:h-full">
          <div class="flex flex-none flex-wrap items-end gap-3 border-b border-hairline bg-surface-sunken/60 px-3.5 py-3 sm:px-5">
            <div class="w-full min-w-0 flex-1 min-[560px]:min-w-56">
              <TextInput
                v-model="search"
                label="Search"
                icon="search"
                placeholder="Search by name, phone or email"
              />
            </div>
            <div class="w-full min-[360px]:w-[calc(50%_-_6px)] min-[560px]:w-44">
              <Select v-model="statusFilter" label="Status">
                <option value="all">All statuses</option>
                <option value="active">Active only</option>
                <option value="inactive">Inactive only</option>
              </Select>
            </div>
            <div class="w-full min-[360px]:w-[calc(50%_-_6px)] min-[560px]:w-48">
              <Select v-model="coverageFilter" label="Shift coverage">
                <option value="all">Any coverage</option>
                <option value="scheduled">Has a shift</option>
                <option value="unscheduled">No shift assigned</option>
              </Select>
            </div>
            <Button v-if="isFiltered" variant="ghost" size="sm" @click="clearFilters">
              <Icon name="close" class="h-3.5 w-3.5" />
              Clear
            </Button>
          </div>

          <div class="min-h-0 lg:flex-1 lg:overflow-y-auto">
            <Table
              embedded
              :headers="['Employee', 'Activity & location', 'Active', 'Pending / Scheduled', 'Workload', '']"
              :loading="isLoading"
              :error="error"
              :is-empty="employees.length === 0"
              :empty-message="isFiltered ? 'No employee matches these filters.' : 'No employees yet — add one to get started.'"
            >
              <template #cards>
                <div v-for="employee in employees" :key="employee.id" class="surface-flat space-y-3 p-3.5 sm:p-4">
                  <div class="flex items-start gap-3">
                    <Avatar :name="employee.name" size="sm" :muted="!employee.is_active" />
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-[14px] font-medium text-ink">{{ employee.name }}</p>
                      <p class="truncate text-[12px] text-ink-faint">{{ employee.email ?? 'No email' }}</p>
                    </div>
                    <Badge :variant="activityOf(employee).variant">
                      {{ activityOf(employee).label }}
                    </Badge>
                  </div>

                  <p class="flex items-center gap-1.5 truncate text-[11.5px] text-ink-faint">
                    <Icon name="map-pin" class="h-3.5 w-3.5 flex-none" />
                    {{ locationOf(employee) }}
                  </p>

                  <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-[13px]">
                    <div><dt class="eyebrow mb-1">Active cases</dt><dd class="tabular text-ink">{{ workloadByEmployee.get(employee.id)?.summary.active_cases ?? 0 }}</dd></div>
                    <div><dt class="eyebrow mb-1">Pending / Scheduled</dt><dd class="tabular text-ink">{{ workloadByEmployee.get(employee.id)?.summary.pending ?? 0 }} / {{ workloadByEmployee.get(employee.id)?.summary.scheduled ?? 0 }}</dd></div>
                    <div class="col-span-2">
                      <dt class="eyebrow mb-1">Workload</dt>
                      <dd class="flex items-center gap-2"><span class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-sunken"><span class="block h-full rounded-full bg-primary" :style="{ width: `${workloadPercent(employee.id)}%` }" /></span><span class="tabular text-[11px] text-ink-faint">{{ workloadPercent(employee.id) }}%</span></dd>
                    </div>
                  </dl>

                  <div class="flex flex-wrap items-center gap-1 border-t border-hairline pt-2.5">
                    <button type="button" class="min-h-10 rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken" @click="openActivity(employee)">
                      Activity
                    </button>
                    <NuxtLink :to="`/employees/${employee.id}`" class="inline-flex min-h-10 items-center rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
                      Schedule
                    </NuxtLink>
                    <NuxtLink :to="`/employees/${employee.id}/histories`" class="inline-flex min-h-10 items-center rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
                      Histories
                    </NuxtLink>
                    <Popover class="ml-auto" :width="228" label="More actions">
                      <template #default="{ close }">
                        <MenuItem icon="pencil" @click="close(); openEdit(employee)">Edit details</MenuItem>
                        <MenuItem icon="key" @click="close(); openChangePassword(employee)">Change password</MenuItem>
                        <MenuItem icon="power" :tone="employee.is_active ? 'danger' : 'default'" @click="close(); toggleActive(employee)">
                          {{ employee.is_active ? 'Deactivate' : 'Activate' }}
                        </MenuItem>
                        <MenuItem v-if="employee.device" icon="smartphone" tone="danger" @click="close(); revokeDevice(employee)">
                          Revoke device
                        </MenuItem>
                        <MenuItem icon="trash" tone="danger" @click="close(); removeEmployee(employee)">Delete employee</MenuItem>
                      </template>
                    </Popover>
                  </div>
                </div>
              </template>

              <tr v-for="employee in employees" :key="employee.id" class="group row-h text-ink transition-colors hover:bg-surface-sunken/60">
                <td class="px-4 sm:px-5">
                  <div class="flex items-center gap-3">
                    <Avatar :name="employee.name" size="sm" :muted="!employee.is_active" />
                    <div class="min-w-0">
                      <div class="truncate text-[14px] font-medium">{{ employee.name }}</div>
                      <div class="truncate text-[12px] text-ink-faint">{{ employee.email ?? 'No email' }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-4 sm:px-5">
                  <Badge :variant="activityOf(employee).variant">
                    {{ activityOf(employee).label }}
                  </Badge>
                  <div class="mt-1 max-w-52 truncate text-[11.5px] text-ink-faint">{{ locationOf(employee) }}</div>
                </td>
                <td class="px-4 text-[14px] font-semibold tabular sm:px-5">{{ workloadByEmployee.get(employee.id)?.summary.active_cases ?? 0 }}</td>
                <td class="px-4 text-[13px] tabular sm:px-5">
                  <span>{{ workloadByEmployee.get(employee.id)?.summary.pending ?? 0 }} pending</span><span class="text-ink-faint"> · {{ workloadByEmployee.get(employee.id)?.summary.scheduled ?? 0 }} scheduled</span>
                  <div v-if="workloadByEmployee.get(employee.id)?.summary.overdue" class="mt-0.5 text-state-danger">{{ workloadByEmployee.get(employee.id)?.summary.overdue }} overdue</div>
                </td>
                <td class="px-4 sm:px-5">
                  <div class="flex min-w-28 items-center gap-2"><span class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-sunken"><span class="block h-full rounded-full" :class="workloadPercent(employee.id) >= 80 ? 'bg-state-danger' : workloadPercent(employee.id) >= 55 ? 'bg-state-warning' : 'bg-state-success'" :style="{ width: `${workloadPercent(employee.id)}%` }" /></span><span class="tabular text-[11px] text-ink-faint">{{ workloadPercent(employee.id) }}%</span></div>
                </td>
                <td class="px-4 sm:px-5">
                  <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                    <button
                      type="button"
                      class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong opacity-0 transition-opacity hover:bg-surface-sunken group-hover:opacity-100 focus-visible:opacity-100"
                      @click="openActivity(employee)"
                    >
                      Activity
                    </button>
                    <NuxtLink
                      :to="`/employees/${employee.id}`"
                      class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong opacity-0 transition-opacity hover:bg-surface-sunken group-hover:opacity-100 focus-visible:opacity-100"
                    >
                      Schedule
                    </NuxtLink>
                    <NuxtLink
                      :to="`/employees/${employee.id}/histories`"
                      class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong opacity-0 transition-opacity hover:bg-surface-sunken group-hover:opacity-100 focus-visible:opacity-100"
                    >
                      Histories
                    </NuxtLink>
                    <Popover :width="228" label="More actions">
                      <template #default="{ close }">
                        <MenuItem icon="pencil" @click="close(); openEdit(employee)">Edit details</MenuItem>
                        <MenuItem icon="key" @click="close(); openChangePassword(employee)">Change password</MenuItem>
                        <MenuItem icon="power" :tone="employee.is_active ? 'danger' : 'default'" @click="close(); toggleActive(employee)">
                          {{ employee.is_active ? 'Deactivate' : 'Activate' }}
                        </MenuItem>
                        <MenuItem v-if="employee.device" icon="smartphone" tone="danger" @click="close(); revokeDevice(employee)">
                          Revoke device
                        </MenuItem>
                        <MenuItem icon="trash" tone="danger" @click="close(); removeEmployee(employee)">Delete employee</MenuItem>
                      </template>
                    </Popover>
                  </div>
                </td>
              </tr>
            </Table>
          </div>
        </div>
      </Card>
    </div>

    <Drawer v-model="activityDrawerOpen" :title="`${activityEmployeeName} · activity`">
      <div class="space-y-4">
        <div>
          <label for="employee-activity-date" class="mb-1.5 block text-[12px] font-medium text-ink-soft">Activity date</label>
          <input
            id="employee-activity-date"
            v-model="activityDate"
            type="date"
            placeholder="Pick an activity date"
            :max="todayLocalDate()"
            class="field w-full"
          />
        </div>

        <InlineAlert v-if="activityError" class="!mb-0">{{ activityError }}</InlineAlert>

        <div v-if="activityLoading" class="space-y-2.5">
          <Skeleton class="h-24" rounded="md" />
          <Skeleton class="h-40" rounded="md" />
        </div>

        <template v-else-if="activityDetail">
          <section class="surface-flat">
            <header class="border-b border-hairline px-4 py-3">
              <h2>Case workload</h2>
              <p class="mt-0.5 text-[12px] text-ink-faint">Operational capacity for the selected surveyor</p>
            </header>
            <div class="grid grid-cols-2 gap-2.5 p-4">
              <StatCard tone="sunken" icon="briefcase" label="Open" :value="String(activityDetail.summary.active_cases)" accent="primary" />
              <StatCard tone="sunken" icon="inbox" label="Awaiting" :value="String(activityDetail.summary.pending)" accent="warning" />
              <StatCard tone="sunken" icon="calendar" label="Scheduled" :value="String(activityDetail.summary.scheduled)" accent="neutral" />
              <StatCard tone="sunken" icon="alert-triangle" label="Overdue" :value="String(activityDetail.summary.overdue)" :accent="activityDetail.summary.overdue > 0 ? 'danger' : 'neutral'" />
            </div>
          </section>

          <section class="surface-flat">
            <header class="flex items-center justify-between gap-2 border-b border-hairline px-4 py-3">
              <div>
                <h2>Time on task</h2>
                <p class="mt-0.5 text-[12px] text-ink-faint">Inspection, travel and idle time</p>
              </div>
              <span class="text-[12px] tabular text-ink-faint">{{ new Date(activityDate).toLocaleDateString() }}</span>
            </header>
            <div class="p-4">
              <div class="flex items-center gap-3.5">
                <div class="relative grid h-16 w-16 flex-none place-items-center rounded-full" :style="activityRingStyle(activityDetail.activity)">
                  <div class="grid h-11 w-11 place-items-center rounded-full bg-surface">
                    <span class="text-[13px] font-bold tabular text-ink">{{ utilizationPercent(activityDetail.activity) }}%</span>
                  </div>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex h-2.5 w-full overflow-hidden rounded-pill bg-surface-sunken">
                    <div class="bg-primary" :style="{ width: activitySegments(activityDetail.activity).inspection + '%' }" />
                    <div class="bg-state-warning" :style="{ width: activitySegments(activityDetail.activity).travel + '%' }" />
                    <div class="bg-state-neutral" :style="{ width: activitySegments(activityDetail.activity).idle + '%' }" />
                  </div>
                  <p class="mt-2 tabular text-[12px] text-ink-faint">{{ formatDistance(activityDetail.activity.distance_m) }} travelled</p>
                </div>
              </div>

              <dl class="mt-4 grid grid-cols-3 gap-2.5 border-t border-hairline pt-4 text-[13px]">
                <div><dt class="eyebrow mb-1">Inspecting</dt><dd class="tabular font-semibold text-ink">{{ minutesLabel(activityDetail.activity.inspection_minutes) }}</dd></div>
                <div><dt class="eyebrow mb-1">Travelling</dt><dd class="tabular font-semibold text-ink">{{ minutesLabel(activityDetail.activity.travel_minutes) }}</dd></div>
                <div><dt class="eyebrow mb-1">Idle</dt><dd class="tabular font-semibold text-ink">{{ minutesLabel(activityDetail.activity.idle_minutes) }}</dd></div>
              </dl>

              <InlineAlert v-if="activityDetail.activity.window_minutes === null" variant="info" class="mt-4 !mb-0">
                No shift window resolved for this day, so no work location was collected.
              </InlineAlert>
            </div>
          </section>

          <Button variant="secondary" class="w-full justify-center" :to="`/employees/${activityDetail.employee_id}/histories`">
            <Icon name="route" class="h-4 w-4" />
            Open route history
          </Button>
        </template>
      </div>
    </Drawer>

    <Modal v-model="editModalOpen" title="Edit employee">
      <form class="space-y-3.5" @submit.prevent="submitEdit">
        <InlineAlert v-if="editError">{{ editError }}</InlineAlert>

        <TextInput v-model="editForm.name" label="Name" placeholder="Full name as it appears on the roster" required />
        <TextInput v-model="editForm.phone" label="Phone" placeholder="e.g. 92000001" required hint="Used to log in on the mobile app." />
        <TextInput v-model="editForm.email" type="email" label="Email" placeholder="name@example.com" required hint="Used to log in, and to receive account emails." />
      </form>

      <template #footer>
        <Button variant="secondary" @click="editModalOpen = false">Cancel</Button>
        <Button :loading="editSaving" @click="submitEdit">
          {{ editSaving ? 'Saving…' : 'Save changes' }}
        </Button>
      </template>
    </Modal>

    <Modal v-model="passwordModalOpen" title="Change password">
      <form class="space-y-3.5" @submit.prevent="submitChangePassword">
        <p class="mb-1">
          Set a new password for <span class="font-medium text-ink">{{ passwordTarget?.name }}</span>. They'll need it
          next time they sign in on the mobile app, and an email with the new password will be sent to them if they
          have an email on file.
        </p>

        <InlineAlert v-if="passwordError">{{ passwordError }}</InlineAlert>

        <TextInput v-model="newPassword" type="password" label="New password" placeholder="At least 8 characters" required :minlength="8" autocomplete="new-password" />
        <TextInput v-model="confirmPassword" type="password" label="Confirm new password" placeholder="Repeat the new password" required :minlength="8" autocomplete="new-password" />
      </form>

      <template #footer>
        <Button variant="secondary" @click="passwordModalOpen = false">Cancel</Button>
        <Button :loading="passwordSaving" @click="submitChangePassword">
          {{ passwordSaving ? 'Changing…' : 'Change password' }}
        </Button>
      </template>
    </Modal>
  </AppShell>
</template>
