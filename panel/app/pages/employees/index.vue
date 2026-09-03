<script setup lang="ts">
import type { Employee } from '~/composables/useEmployees'
import type { InspectionCase } from '~/composables/useCases'
import { casePriorityLabel, casePriorityVariant, caseStatusLabel, caseStatusVariant } from '~/utils/caseStatus'

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

const activityDrawerOpen = ref(false)
const activityEmployeeId = ref<number | null>(null)
const activityEmployeeName = ref('')
const assignedCases = ref<InspectionCase[]>([])
const activityLoading = ref(false)
const activityError = ref<string | null>(null)

function dateTimeLabel(value: string | null): string {
  return value ? new Date(value).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : 'Not set'
}

async function loadActivityDetail() {
  if (activityEmployeeId.value === null) return
  activityLoading.value = true
  activityError.value = null
  try {
    assignedCases.value = await apiFetch<InspectionCase[]>(`/api/v1/employees/${activityEmployeeId.value}/assigned-cases`)
  } catch (err) {
    activityError.value = apiErrorMessage(err, 'Could not load assigned cases for this employee.')
  } finally {
    activityLoading.value = false
  }
}

function openActivity(employee: Employee) {
  activityEmployeeId.value = employee.id
  activityEmployeeName.value = employee.name
  assignedCases.value = []
  visibleCaseCount.value = PAGE_SIZE
  activityDrawerOpen.value = true
  loadActivityDetail()
}

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

interface EmployeeLeave {
  id: number
  employee_id: number
  starts_at: string
  ends_at: string
  note: string | null
  created_by: string | null
  created_at: string | null
}

const PAGE_SIZE = 15

function toLocalInput(date: Date): string {
  const pad = (value: number) => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const leaveModalOpen = ref(false)
const leaveTarget = ref<Employee | null>(null)
const leaveSaving = ref(false)
const leaveError = ref<string | null>(null)
const leaveForm = reactive({ starts_at: '', ends_at: '', note: '' })

function openRecordLeave(employee: Employee) {
  const start = new Date()
  start.setMinutes(0, 0, 0)
  start.setHours(start.getHours() + 1)
  const end = new Date(start)
  end.setHours(end.getHours() + 8)

  leaveTarget.value = employee
  leaveForm.starts_at = toLocalInput(start)
  leaveForm.ends_at = toLocalInput(end)
  leaveForm.note = ''
  leaveError.value = null
  leaveModalOpen.value = true
}

async function submitLeave() {
  if (!leaveTarget.value) return

  if (!leaveForm.starts_at || !leaveForm.ends_at) {
    leaveError.value = 'Pick when the leave starts and when it ends.'
    return
  }

  const starts = new Date(leaveForm.starts_at)
  const ends = new Date(leaveForm.ends_at)

  if (ends <= starts) {
    leaveError.value = 'The leave must end after it starts.'
    return
  }

  leaveSaving.value = true
  leaveError.value = null
  try {
    await apiFetch(`/api/v1/employees/${leaveTarget.value.id}/leaves`, {
      method: 'POST',
      body: {
        starts_at: starts.toISOString(),
        ends_at: ends.toISOString(),
        note: leaveForm.note.trim() === '' ? null : leaveForm.note.trim(),
      },
    })
    toast.success('Leave recorded — tracking stays off for that whole range.')
    leaveModalOpen.value = false

    if (leaveHistoryTarget.value?.id === leaveTarget.value.id) {
      await loadLeaves(1)
    }
  } catch (err) {
    leaveError.value = apiErrorMessage(err, 'Could not record the leave.')
  } finally {
    leaveSaving.value = false
  }
}

const leaveHistoryOpen = ref(false)
const leaveHistoryTarget = ref<Employee | null>(null)
const leaves = ref<EmployeeLeave[]>([])
const leavesPage = ref(1)
const leavesLastPage = ref(1)
const leavesTotal = ref(0)
const leavesLoading = ref(false)
const leavesError = ref<string | null>(null)

async function loadLeaves(page: number) {
  if (!leaveHistoryTarget.value) return

  leavesLoading.value = true
  leavesError.value = null
  try {
    const response = await apiFetch<{ data: EmployeeLeave[], meta: { current_page: number, last_page: number, total: number } }>(
      `/api/v1/employees/${leaveHistoryTarget.value.id}/leaves?page=${page}&per_page=${PAGE_SIZE}`,
    )
    leaves.value = page === 1 ? response.data : [...leaves.value, ...response.data]
    leavesPage.value = response.meta.current_page
    leavesLastPage.value = response.meta.last_page
    leavesTotal.value = response.meta.total
  } catch (err) {
    leavesError.value = apiErrorMessage(err, 'Could not load this employee\'s leaves.')
  } finally {
    leavesLoading.value = false
  }
}

function openLeaveHistory(employee: Employee) {
  leaveHistoryTarget.value = employee
  leaves.value = []
  leavesPage.value = 1
  leavesLastPage.value = 1
  leavesTotal.value = 0
  leaveHistoryOpen.value = true
  loadLeaves(1)
}

const hasOlderLeaves = computed(() => leavesPage.value < leavesLastPage.value)

async function cancelLeave(leave: EmployeeLeave) {
  const confirmed = await confirm(
    'Cancel this leave? Tracking goes back to the employee\'s normal shift for that range.',
    { title: 'Cancel leave', variant: 'danger' },
  )
  if (!confirmed) return

  try {
    await apiFetch(`/api/v1/employee-leaves/${leave.id}`, { method: 'DELETE' })
    toast.success('Leave cancelled.')
    await loadLeaves(1)
  } catch (err) {
    toast.error(apiErrorMessage(err, 'Could not cancel the leave.'))
  }
}

function leaveRangeLabel(leave: EmployeeLeave): string {
  return `${dateTimeLabel(leave.starts_at)} → ${dateTimeLabel(leave.ends_at)}`
}

function leaveState(leave: EmployeeLeave): { label: string, variant: 'success' | 'warning' | 'neutral' } {
  const now = Date.now()
  if (new Date(leave.ends_at).getTime() <= now) return { label: 'Past', variant: 'neutral' }
  if (new Date(leave.starts_at).getTime() <= now) return { label: 'On leave now', variant: 'warning' }
  return { label: 'Upcoming', variant: 'success' }
}

const visibleCaseCount = ref(PAGE_SIZE)
const visibleCases = computed(() => assignedCases.value.slice(0, visibleCaseCount.value))
const hasOlderCases = computed(() => assignedCases.value.length > visibleCaseCount.value)

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
      <Card
        class="flex-none lg:min-h-0 lg:flex-1"
        icon="users"
        title="Workforce & workload"
        :subtitle="`${employees.length} shown of ${allEmployees.length} · live operational capacity`"
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
                        <MenuItem icon="calendar" @click="close(); openRecordLeave(employee)">Record leave</MenuItem>
                        <MenuItem icon="history" @click="close(); openLeaveHistory(employee)">Leave history</MenuItem>
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
                      class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken"
                      @click="openActivity(employee)"
                    >
                      Activity
                    </button>
                    <NuxtLink
                      :to="`/employees/${employee.id}`"
                      class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken"
                    >
                      Schedule
                    </NuxtLink>
                    <NuxtLink
                      :to="`/employees/${employee.id}/histories`"
                      class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken"
                    >
                      Histories
                    </NuxtLink>
                    <Popover :width="228" label="More actions">
                      <template #default="{ close }">
                        <MenuItem icon="pencil" @click="close(); openEdit(employee)">Edit details</MenuItem>
                        <MenuItem icon="key" @click="close(); openChangePassword(employee)">Change password</MenuItem>
                        <MenuItem icon="calendar" @click="close(); openRecordLeave(employee)">Record leave</MenuItem>
                        <MenuItem icon="history" @click="close(); openLeaveHistory(employee)">Leave history</MenuItem>
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

    <Drawer v-model="activityDrawerOpen" :title="`${activityEmployeeName} · assigned cases`">
      <div class="space-y-3">
        <InlineAlert v-if="activityError" class="!mb-0">{{ activityError }}</InlineAlert>

        <div v-if="activityLoading" class="space-y-2.5">
          <Skeleton v-for="i in 5" :key="i" class="h-24" rounded="md" />
        </div>

        <EmptyState
          v-else-if="assignedCases.length === 0"
          icon="briefcase"
          message="No cases have been assigned to this employee yet."
        />

        <NuxtLink
          v-for="caseItem in visibleCases"
          v-else
          :key="caseItem.id"
          :to="`/cases/${caseItem.id}`"
          class="block rounded-md border border-hairline bg-surface p-4 transition-colors hover:border-primary/40 hover:bg-surface-sunken/60"
          @click="activityDrawerOpen = false"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate text-[14px] font-semibold text-ink">{{ caseItem.title }}</p>
              <p class="mt-0.5 truncate text-[12px] text-ink-faint">{{ caseItem.reference_no }}</p>
            </div>
            <Badge :variant="caseStatusVariant(caseItem.status)">{{ caseStatusLabel(caseItem.status) }}</Badge>
          </div>
          <p class="mt-3 line-clamp-2 text-[12.5px] leading-5 text-ink-soft">
            {{ caseItem.property_address || 'Property address not provided' }}
          </p>
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <Badge :variant="casePriorityVariant(caseItem.priority)">{{ casePriorityLabel(caseItem.priority) }} priority</Badge>
            <span class="tabular text-[11.5px] text-ink-faint">Assigned {{ dateTimeLabel(caseItem.assigned_at) }}</span>
          </div>
        </NuxtLink>

        <div v-if="hasOlderCases" class="flex justify-center pt-1">
          <Button variant="secondary" size="sm" @click="visibleCaseCount += PAGE_SIZE">
            Show older
          </Button>
        </div>
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

    <Modal v-model="leaveModalOpen" title="Record leave">
      <form class="space-y-3.5" @submit.prevent="submitLeave">
        <p class="mb-1">
          <span class="font-medium text-ink">{{ leaveTarget?.name }}</span> is off for one continuous range. Their shift
          does not open inside it, no location is recorded, and nothing about them appears on the live map until it ends.
        </p>

        <InlineAlert v-if="leaveError">{{ leaveError }}</InlineAlert>

        <TextInput
          v-model="leaveForm.starts_at"
          type="datetime-local"
          label="Leave starts"
          required
          hint="A leave can only start from now on — it never changes what was already recorded."
        />
        <TextInput v-model="leaveForm.ends_at" type="datetime-local" label="Leave ends" required />
        <TextInput v-model="leaveForm.note" label="Note" placeholder="Optional — e.g. annual leave" />
      </form>

      <template #footer>
        <Button variant="secondary" @click="leaveModalOpen = false">Cancel</Button>
        <Button :loading="leaveSaving" @click="submitLeave">
          {{ leaveSaving ? 'Saving…' : 'Record leave' }}
        </Button>
      </template>
    </Modal>

    <Modal v-model="leaveHistoryOpen" :title="`${leaveHistoryTarget?.name ?? 'Employee'} · leave history`">
      <div class="space-y-2.5">
        <InlineAlert v-if="leavesError" class="!mb-0">{{ leavesError }}</InlineAlert>

        <div v-if="leavesLoading && leaves.length === 0" class="space-y-2">
          <Skeleton v-for="i in 4" :key="i" class="h-16" rounded="md" />
        </div>

        <EmptyState
          v-else-if="leaves.length === 0"
          icon="calendar"
          message="No leave has been recorded for this employee."
        />

        <template v-else>
          <p class="text-[11.5px] text-ink-faint">
            Showing {{ leaves.length }} of {{ leavesTotal }}, newest first.
          </p>

          <div
            v-for="leave in leaves"
            :key="leave.id"
            class="rounded-md border border-hairline bg-surface p-3"
          >
            <div class="flex items-start justify-between gap-3">
              <p class="tabular text-[13px] font-medium text-ink">{{ leaveRangeLabel(leave) }}</p>
              <Badge :variant="leaveState(leave).variant">{{ leaveState(leave).label }}</Badge>
            </div>
            <p v-if="leave.note" class="mt-1.5 text-[12.5px] text-ink-soft">{{ leave.note }}</p>
            <div class="mt-2 flex items-center justify-between gap-3">
              <span class="text-[11.5px] text-ink-faint">
                Recorded {{ dateTimeLabel(leave.created_at) }}<template v-if="leave.created_by"> by {{ leave.created_by }}</template>
              </span>
              <Button
                v-if="leaveState(leave).label !== 'Past'"
                variant="ghost"
                size="sm"
                @click="cancelLeave(leave)"
              >
                Cancel leave
              </Button>
            </div>
          </div>

          <div v-if="hasOlderLeaves" class="flex justify-center pt-1">
            <Button variant="secondary" size="sm" :loading="leavesLoading" @click="loadLeaves(leavesPage + 1)">
              Show older
            </Button>
          </div>
        </template>
      </div>
    </Modal>
  </AppShell>
</template>
