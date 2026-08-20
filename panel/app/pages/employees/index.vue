<script setup lang="ts">
import type { Employee } from '~/composables/useEmployees'

const { data: employeesData, loading, error: cacheError, load, refresh } = useEmployees()
const employees = computed(() => employeesData.value ?? [])
const error = computed(() => (cacheError.value ? 'Could not load employees. Sign in and try again.' : null))

const { confirm } = useConfirm()
const toast = useToast()

const passwordModalOpen = ref(false)
const passwordTarget = ref<Employee | null>(null)
const passwordSaving = ref(false)
const passwordError = ref<string | null>(null)
const newPassword = ref('')
const confirmPassword = ref('')

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
  const confirmed = await confirm(`${next ? 'Activate' : 'Deactivate'} ${employee.name}?`, {
    title: next ? 'Activate employee' : 'Deactivate employee',
    variant: next ? 'default' : 'danger',
  })
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

onMounted(load)
</script>

<template>
  <AppShell title="Employees" :subtitle="`${employees.length} total`">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" @click="refresh">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
      <Button size="sm" to="/employees/create">
        <Icon name="plus" class="h-3.5 w-3.5" />
        <span class="hidden sm:inline">Add employee</span>
      </Button>
    </template>

    <Table
      :headers="['Employee', 'Phone', 'Shifts', 'Status', 'Device', '']"
      :loading="loading"
      :error="error"
      :is-empty="employees.length === 0"
      empty-message="No employees yet — add one to get started."
    >
      <template #cards>
        <div v-for="employee in employees" :key="employee.id" class="surface-flat space-y-3 p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate text-[14px] font-medium text-ink">{{ employee.name }}</p>
              <p class="truncate text-[12px] text-ink-faint">{{ employee.username ?? employee.email ?? '—' }}</p>
            </div>
            <Badge :variant="employee.is_active ? 'success' : 'neutral'">
              {{ employee.is_active ? 'Active' : 'Inactive' }}
            </Badge>
          </div>

          <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-[13px]">
            <div>
              <dt class="eyebrow mb-1">Phone</dt>
              <dd class="tabular text-ink">{{ employee.phone ?? '—' }}</dd>
            </div>
            <div>
              <dt class="eyebrow mb-1">Device</dt>
              <dd class="truncate text-ink">
                {{ employee.device ? (employee.device.device_name ?? employee.device.device_identifier) : '—' }}
              </dd>
            </div>
            <div class="col-span-2">
              <dt class="eyebrow mb-1">Shifts</dt>
              <dd>
                <div v-if="employee.shifts.length" class="flex flex-wrap gap-1.5">
                  <Badge v-for="shift in employee.shifts" :key="shift.id" variant="neutral">
                    {{ shift.name }} · {{ shift.start_time.slice(0, 5) }}–{{ shift.end_time.slice(0, 5) }}
                  </Badge>
                </div>
                <span v-else class="text-ink-faint">No shifts</span>
              </dd>
            </div>
          </dl>

          <div class="flex flex-wrap items-center gap-1 border-t border-hairline pt-2.5">
            <NuxtLink :to="`/employees/${employee.id}`" class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
              Schedule
            </NuxtLink>
            <NuxtLink :to="`/employees/${employee.id}/histories`" class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
              Histories
            </NuxtLink>
            <button type="button" class="rounded-sm px-2.5 py-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-ink" @click="openChangePassword(employee)">
              Change password
            </button>
            <button type="button" class="rounded-sm px-2.5 py-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" @click="toggleActive(employee)">
              {{ employee.is_active ? 'Deactivate' : 'Activate' }}
            </button>
            <button v-if="employee.device" type="button" class="rounded-sm px-2.5 py-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" @click="revokeDevice(employee)">
              Revoke device
            </button>
            <button type="button" class="ml-auto rounded-sm p-2 text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" title="Delete employee" aria-label="Delete employee" @click="removeEmployee(employee)">
              <Icon name="trash" class="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      </template>

      <tr v-for="employee in employees" :key="employee.id" class="row-h text-ink hover:bg-surface-sunken/60">
        <td class="px-5">
          <div class="text-[14px] font-medium">{{ employee.name }}</div>
          <div class="text-[12px] text-ink-faint">{{ employee.username ?? employee.email ?? '—' }}</div>
        </td>
        <td class="px-5 text-[14px] tabular">{{ employee.phone ?? '—' }}</td>
        <td class="px-5">
          <div v-if="employee.shifts.length" class="flex max-w-56 flex-wrap gap-1.5">
            <Badge v-for="shift in employee.shifts" :key="shift.id" variant="neutral">
              {{ shift.name }} · {{ shift.start_time.slice(0, 5) }}–{{ shift.end_time.slice(0, 5) }}
            </Badge>
          </div>
          <span v-else class="text-[13px] text-ink-faint">No shifts</span>
        </td>
        <td class="px-5">
          <Badge :variant="employee.is_active ? 'success' : 'neutral'">
            {{ employee.is_active ? 'Active' : 'Inactive' }}
          </Badge>
        </td>
        <td class="px-5">
          <template v-if="employee.device">
            <div class="truncate text-[14px]">{{ employee.device.device_name ?? employee.device.device_identifier }}</div>
            <div class="text-[12px] text-ink-faint">
              {{ employee.device.last_seen_at ? new Date(employee.device.last_seen_at).toLocaleDateString() : 'never seen' }}
            </div>
          </template>
          <span v-else class="text-[13px] text-ink-faint">—</span>
        </td>
        <td class="px-5">
          <div class="flex items-center justify-end gap-1 whitespace-nowrap">
            <NuxtLink :to="`/employees/${employee.id}`" class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
              Schedule
            </NuxtLink>
            <NuxtLink :to="`/employees/${employee.id}/histories`" class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
              Histories
            </NuxtLink>
            <button type="button" class="rounded-sm px-2.5 py-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-ink" @click="openChangePassword(employee)">
              Change password
            </button>
            <button type="button" class="rounded-sm px-2.5 py-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" @click="toggleActive(employee)">
              {{ employee.is_active ? 'Deactivate' : 'Activate' }}
            </button>
            <button v-if="employee.device" type="button" class="rounded-sm px-2.5 py-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" @click="revokeDevice(employee)">
              Revoke device
            </button>
            <button type="button" class="rounded-sm p-2 text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" title="Delete employee" aria-label="Delete employee" @click="removeEmployee(employee)">
              <Icon name="trash" class="h-3.5 w-3.5" />
            </button>
          </div>
        </td>
      </tr>
    </Table>

    <Modal v-model="passwordModalOpen" title="Change password">
      <form class="space-y-3.5" @submit.prevent="submitChangePassword">
        <p class="mb-1">
          Set a new password for <span class="font-medium text-ink">{{ passwordTarget?.name }}</span>. They'll need it
          next time they sign in on the mobile app.
        </p>

        <InlineAlert v-if="passwordError">{{ passwordError }}</InlineAlert>

        <TextInput v-model="newPassword" type="password" label="New password" required :minlength="8" autocomplete="new-password" />
        <TextInput v-model="confirmPassword" type="password" label="Confirm new password" required :minlength="8" autocomplete="new-password" />
      </form>

      <template #footer>
        <Button variant="secondary" @click="passwordModalOpen = false">Cancel</Button>
        <Button :disabled="passwordSaving" @click="submitChangePassword">
          {{ passwordSaving ? 'Changing…' : 'Change password' }}
        </Button>
      </template>
    </Modal>
  </AppShell>
</template>
