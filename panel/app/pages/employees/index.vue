<script setup lang="ts">
import type { Employee } from '~/composables/useEmployees'
import type { InspectionCase } from '~/composables/useCases'
import { casePriorityVariant, caseStatusVariant } from '~/utils/caseStatus'

const { t } = useI18n()
const { dateTime, number } = useLocalizedFormat()

const { data: employeesData, loading, error: cacheError, load, refresh } = useEmployees()
const { data: workloadData, loading: workloadLoading, load: loadWorkload } = useWorkloadList()
const allEmployees = computed(() => employeesData.value ?? [])
const error = computed(() => (cacheError.value ? t('employees.list.loadFailed') : null))

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
  if (!employee.is_active) return { label: t('common.inactive'), variant: 'neutral' }
  const summary = workloadByEmployee.value.get(employee.id)?.summary
  if ((summary?.in_progress ?? 0) > 0) return { label: t('employees.activity.inspecting'), variant: 'success' }
  if ((summary?.overdue ?? 0) > 0) return { label: t('employees.activity.overdue'), variant: 'danger' }
  if ((summary?.pending ?? 0) > 0) return { label: t('employees.activity.awaiting'), variant: 'warning' }
  if ((summary?.scheduled ?? 0) > 0) return { label: t('case.assignmentStatuses.scheduled'), variant: 'success' }
  return { label: t('employees.activity.available'), variant: 'neutral' }
}

function locationOf(employee: Employee): string {
  const position = positionByEmployee.value.get(employee.id)
  if (!position) return employee.is_active ? t('employees.location.offShift') : t('employees.location.unavailable')
  return `${position.lat.toFixed(4)}, ${position.lng.toFixed(4)} · ${t(`employees.connection.${position.connection_status}`)}`
}

const activityDrawerOpen = ref(false)
const activityEmployeeId = ref<number | null>(null)
const activityEmployeeName = ref('')
const assignedCases = ref<InspectionCase[]>([])
const activityLoading = ref(false)
const activityError = ref<string | null>(null)

function dateTimeLabel(value: string | null): string {
  return value ? dateTime(value) : t('employees.list.notSet')
}

async function loadActivityDetail() {
  if (activityEmployeeId.value === null) return
  activityLoading.value = true
  activityError.value = null
  try {
    assignedCases.value = await apiFetch<InspectionCase[]>(`/api/v1/employees/${activityEmployeeId.value}/assigned-cases`)
  } catch (err) {
    activityError.value = apiErrorMessage(err, t('employees.list.assignedCasesFailed'))
  } finally {
    activityLoading.value = false
  }
}

function openActivity(employee: Employee) {
  activityEmployeeId.value = employee.id
  activityEmployeeName.value = employee.name
  assignedCases.value = []
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
    toast.success(t('employees.list.updated'))
    editModalOpen.value = false
    await refresh()
  } catch (err) {
    editError.value = apiErrorMessage(err, t('employees.list.updateFailed'))
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
    passwordError.value = t('employees.list.passwordLength')
    return
  }
  if (newPassword.value !== confirmPassword.value) {
    passwordError.value = t('employees.list.passwordMismatch')
    return
  }

  passwordSaving.value = true
  passwordError.value = null
  try {
    await apiFetch(`/api/v1/employees/${passwordTarget.value.id}/password`, {
      method: 'PUT',
      body: { password: newPassword.value, password_confirmation: confirmPassword.value },
    })
    toast.success(t('employees.list.passwordChanged'))
    passwordModalOpen.value = false
  } catch (err) {
    passwordError.value = apiErrorMessage(err, t('employees.list.passwordChangeFailed'))
  } finally {
    passwordSaving.value = false
  }
}

async function toggleActive(employee: Employee) {
  const next = !employee.is_active
  const confirmed = await confirm(
    next
      ? t('employees.list.activateConfirm', { name: employee.name })
      : t('employees.list.deactivateConfirm', { name: employee.name }),
    {
      title: next ? t('employees.list.activateTitle') : t('employees.list.deactivateTitle'),
      variant: next ? 'default' : 'danger',
    },
  )
  if (!confirmed) return
  try {
    await apiFetch(`/api/v1/employees/${employee.id}/active`, { method: 'PUT', body: { is_active: next } })
    toast.success(next ? t('employees.list.activated') : t('employees.list.deactivated'))
    await refresh()
  } catch (err) {
    toast.error(apiErrorMessage(err, t('employees.list.genericUpdateFailed')))
  }
}

async function revokeDevice(employee: Employee) {
  const confirmed = await confirm(t('employees.list.revokeConfirm', { name: employee.name }), {
    title: t('employees.list.revokeDevice'),
    variant: 'danger',
  })
  if (!confirmed) return
  try {
    await apiFetch(`/api/v1/employees/${employee.id}/device`, { method: 'DELETE' })
    toast.success(t('employees.list.deviceRevoked'))
    await refresh()
  } catch (err) {
    toast.error(apiErrorMessage(err, t('employees.list.revokeFailed')))
  }
}

async function removeEmployee(employee: Employee) {
  const confirmed = await confirm(
    t('employees.list.deleteConfirm', { name: employee.name }),
    { title: t('employees.list.deleteEmployee'), variant: 'danger' },
  )
  if (!confirmed) return
  try {
    await apiFetch(`/api/v1/employees/${employee.id}`, { method: 'DELETE' })
    toast.success(t('employees.list.deleted'))
    await refresh()
  } catch (err) {
    toast.error(apiErrorMessage(err, t('employees.list.deleteFailed')))
  }
}

onMounted(() => Promise.all([load(), loadWorkload()]))
</script>

<template>
  <AppShell :title="t('employees.list.title')" :subtitle="t('employees.list.subtitle')" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="isLoading" :aria-label="t('employees.list.refresh')" @click="refreshAll">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="isLoading" />
        <span class="hidden sm:inline">{{ t('common.refresh') }}</span>
      </Button>
    </template>

    <div class="flex h-full min-h-0 flex-col gap-3 overflow-y-auto p-3 sm:gap-4 sm:p-5 lg:overflow-hidden">
      <div class="flex flex-none justify-end">
        <Button class="w-full sm:w-auto" to="/employees/create">
          <Icon name="plus" class="h-4 w-4" />
          <span>{{ t('employees.create.title') }}</span>
        </Button>
      </div>

      <Card
        class="flex-none lg:min-h-0 lg:flex-1"
        icon="users"
        :title="t('employees.list.workforce')"
        :subtitle="t('employees.list.shown', { shown: number(employees.length), total: number(allEmployees.length) })"
        flush
      >
        <div class="flex min-h-0 flex-col lg:h-full">
          <div class="flex flex-none flex-wrap items-end gap-3 border-b border-hairline bg-surface-sunken/60 px-3.5 py-3 sm:px-5">
            <div class="w-full min-w-0 flex-1 min-[560px]:min-w-56">
              <TextInput
                v-model="search"
                :label="t('common.search')"
                icon="search"
                :placeholder="t('employees.list.searchPlaceholder')"
              />
            </div>
            <div class="w-full min-[360px]:w-[calc(50%_-_6px)] min-[560px]:w-44">
              <Select v-model="statusFilter" :label="t('common.status')">
                <option value="all">{{ t('employees.list.allStatuses') }}</option>
                <option value="active">{{ t('employees.list.activeOnly') }}</option>
                <option value="inactive">{{ t('employees.list.inactiveOnly') }}</option>
              </Select>
            </div>
            <div class="w-full min-[360px]:w-[calc(50%_-_6px)] min-[560px]:w-48">
              <Select v-model="coverageFilter" :label="t('employees.list.shiftCoverage')">
                <option value="all">{{ t('employees.list.anyCoverage') }}</option>
                <option value="scheduled">{{ t('employees.list.hasShift') }}</option>
                <option value="unscheduled">{{ t('employees.list.noShift') }}</option>
              </Select>
            </div>
            <Button v-if="isFiltered" variant="ghost" size="sm" @click="clearFilters">
              <Icon name="close" class="h-3.5 w-3.5" />
              {{ t('employees.list.clear') }}
            </Button>
          </div>

          <div class="min-h-0 lg:flex-1 lg:overflow-y-auto">
            <Table
              embedded
              :headers="[t('employees.list.employee'), t('employees.list.activityLocation'), t('employees.list.activeCases'), t('employees.list.pendingScheduled'), t('employees.list.workload'), '']"
              :loading="isLoading"
              :error="error"
              :is-empty="employees.length === 0"
              :empty-message="isFiltered ? t('employees.list.emptyFiltered') : t('employees.list.empty')"
            >
              <template #cards>
                <div v-for="employee in employees" :key="employee.id" class="surface-flat space-y-3 p-3.5 sm:p-4">
                  <div class="flex items-start gap-3">
                    <Avatar :name="employee.name" size="sm" :muted="!employee.is_active" />
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-[14px] font-medium text-ink">{{ employee.name }}</p>
                      <p class="truncate text-[12px] text-ink-faint">{{ employee.email ?? t('employees.list.noEmail') }}</p>
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
                    <div><dt class="eyebrow mb-1">{{ t('employees.list.activeCases') }}</dt><dd class="tabular text-ink">{{ number(workloadByEmployee.get(employee.id)?.summary.active_cases ?? 0) }}</dd></div>
                    <div><dt class="eyebrow mb-1">{{ t('employees.list.pendingScheduled') }}</dt><dd class="tabular text-ink">{{ number(workloadByEmployee.get(employee.id)?.summary.pending ?? 0) }} / {{ number(workloadByEmployee.get(employee.id)?.summary.scheduled ?? 0) }}</dd></div>
                    <div class="col-span-2">
                      <dt class="eyebrow mb-1">{{ t('employees.list.workload') }}</dt>
                      <dd class="flex items-center gap-2"><span class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-sunken"><span class="block h-full rounded-full bg-primary" :style="{ width: `${workloadPercent(employee.id)}%` }" /></span><span class="tabular text-[11px] text-ink-faint">{{ workloadPercent(employee.id) }}%</span></dd>
                    </div>
                  </dl>

                  <div class="flex flex-wrap items-center gap-1 border-t border-hairline pt-2.5">
                    <button type="button" class="min-h-10 rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken" @click="openActivity(employee)">
                      {{ t('employees.list.activity') }}
                    </button>
                    <NuxtLink :to="`/employees/${employee.id}`" class="inline-flex min-h-10 items-center rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
                      {{ t('employees.list.schedule') }}
                    </NuxtLink>
                    <NuxtLink :to="`/employees/${employee.id}/histories`" class="inline-flex min-h-10 items-center rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
                      {{ t('employees.list.histories') }}
                    </NuxtLink>
                    <Popover class="ms-auto" :width="228" :label="t('common.moreActions')">
                      <template #default="{ close }">
                        <MenuItem icon="pencil" @click="close(); openEdit(employee)">{{ t('employees.list.editDetails') }}</MenuItem>
                        <MenuItem icon="key" @click="close(); openChangePassword(employee)">{{ t('employees.list.changePassword') }}</MenuItem>
                        <MenuItem icon="power" :tone="employee.is_active ? 'danger' : 'default'" @click="close(); toggleActive(employee)">
                          {{ employee.is_active ? t('employees.list.deactivate') : t('employees.list.activate') }}
                        </MenuItem>
                        <MenuItem v-if="employee.device" icon="smartphone" tone="danger" @click="close(); revokeDevice(employee)">
                          {{ t('employees.list.revokeDevice') }}
                        </MenuItem>
                        <MenuItem icon="trash" tone="danger" @click="close(); removeEmployee(employee)">{{ t('employees.list.deleteEmployee') }}</MenuItem>
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
                      <div class="truncate text-[12px] text-ink-faint">{{ employee.email ?? t('employees.list.noEmail') }}</div>
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
                  <span>{{ t('employees.list.pendingCount', { count: number(workloadByEmployee.get(employee.id)?.summary.pending ?? 0) }) }}</span><span class="text-ink-faint"> · {{ t('employees.list.scheduledCount', { count: number(workloadByEmployee.get(employee.id)?.summary.scheduled ?? 0) }) }}</span>
                  <div v-if="workloadByEmployee.get(employee.id)?.summary.overdue" class="mt-0.5 text-state-danger">{{ t('employees.list.overdueCount', { count: number(workloadByEmployee.get(employee.id)?.summary.overdue ?? 0) }) }}</div>
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
                      {{ t('employees.list.activity') }}
                    </button>
                    <NuxtLink
                      :to="`/employees/${employee.id}`"
                      class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken"
                    >
                      {{ t('employees.list.schedule') }}
                    </NuxtLink>
                    <NuxtLink
                      :to="`/employees/${employee.id}/histories`"
                      class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken"
                    >
                      {{ t('employees.list.histories') }}
                    </NuxtLink>
                    <Popover :width="228" :label="t('common.moreActions')">
                      <template #default="{ close }">
                        <MenuItem icon="pencil" @click="close(); openEdit(employee)">{{ t('employees.list.editDetails') }}</MenuItem>
                        <MenuItem icon="key" @click="close(); openChangePassword(employee)">{{ t('employees.list.changePassword') }}</MenuItem>
                        <MenuItem icon="power" :tone="employee.is_active ? 'danger' : 'default'" @click="close(); toggleActive(employee)">
                          {{ employee.is_active ? t('employees.list.deactivate') : t('employees.list.activate') }}
                        </MenuItem>
                        <MenuItem v-if="employee.device" icon="smartphone" tone="danger" @click="close(); revokeDevice(employee)">
                          {{ t('employees.list.revokeDevice') }}
                        </MenuItem>
                        <MenuItem icon="trash" tone="danger" @click="close(); removeEmployee(employee)">{{ t('employees.list.deleteEmployee') }}</MenuItem>
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

    <Drawer v-model="activityDrawerOpen" :title="t('employees.list.assignedCasesTitle', { name: activityEmployeeName })">
      <div class="space-y-3">
        <InlineAlert v-if="activityError" class="!mb-0">{{ activityError }}</InlineAlert>

        <div v-if="activityLoading" class="space-y-2.5">
          <Skeleton v-for="i in 5" :key="i" class="h-24" rounded="md" />
        </div>

        <EmptyState
          v-else-if="assignedCases.length === 0"
          icon="briefcase"
          :message="t('employees.list.noAssignedCases')"
        />

        <NuxtLink
          v-for="caseItem in assignedCases"
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
            <Badge :variant="caseStatusVariant(caseItem.status)">{{ t(`case.statuses.${caseItem.status}`) }}</Badge>
          </div>
          <p class="mt-3 line-clamp-2 text-[12.5px] leading-5 text-ink-soft">
            {{ caseItem.property_address || t('employees.list.addressMissing') }}
          </p>
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <Badge :variant="casePriorityVariant(caseItem.priority)">{{ t('employees.list.priorityValue', { priority: t(`case.priorities.${caseItem.priority}`) }) }}</Badge>
            <span class="tabular text-[11.5px] text-ink-faint">{{ t('employees.list.assignedAt', { date: dateTimeLabel(caseItem.assigned_at) }) }}</span>
          </div>
        </NuxtLink>
      </div>
    </Drawer>

    <Modal v-model="editModalOpen" :title="t('employees.list.editEmployee')">
      <form class="space-y-3.5" @submit.prevent="submitEdit">
        <InlineAlert v-if="editError">{{ editError }}</InlineAlert>

        <TextInput v-model="editForm.name" :label="t('common.name')" :placeholder="t('employees.list.fullNamePlaceholder')" required />
        <TextInput v-model="editForm.phone" :label="t('common.phone')" :placeholder="t('employees.placeholders.phone')" required :hint="t('employees.list.phoneLoginHint')" />
        <TextInput v-model="editForm.email" type="email" :label="t('common.email')" :placeholder="t('employees.placeholders.email')" required :hint="t('employees.list.emailLoginHint')" />
      </form>

      <template #footer>
        <Button variant="secondary" @click="editModalOpen = false">{{ t('common.cancel') }}</Button>
        <Button :loading="editSaving" @click="submitEdit">
          {{ editSaving ? t('common.saving') : t('employees.list.saveChanges') }}
        </Button>
      </template>
    </Modal>

    <Modal v-model="passwordModalOpen" :title="t('employees.list.changePassword')">
      <form class="space-y-3.5" @submit.prevent="submitChangePassword">
        <p class="mb-1">
          {{ t('employees.list.passwordIntro', { name: passwordTarget?.name }) }}
        </p>

        <InlineAlert v-if="passwordError">{{ passwordError }}</InlineAlert>

        <TextInput v-model="newPassword" type="password" :label="t('employees.list.newPassword')" :placeholder="t('employees.placeholders.password')" required :minlength="8" autocomplete="new-password" />
        <TextInput v-model="confirmPassword" type="password" :label="t('employees.list.confirmPassword')" :placeholder="t('employees.list.repeatPassword')" required :minlength="8" autocomplete="new-password" />
      </form>

      <template #footer>
        <Button variant="secondary" @click="passwordModalOpen = false">{{ t('common.cancel') }}</Button>
        <Button :loading="passwordSaving" @click="submitChangePassword">
          {{ passwordSaving ? t('employees.list.changing') : t('employees.list.changePassword') }}
        </Button>
      </template>
    </Modal>
  </AppShell>
</template>
