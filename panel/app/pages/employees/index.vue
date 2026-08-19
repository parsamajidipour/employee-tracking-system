<script setup lang="ts">
import type { Employee } from '~/composables/useEmployees'

const { data: employeesData, loading, error: cacheError, load, refresh } = useEmployees()
const employees = computed(() => employeesData.value ?? [])
const error = computed(() => (cacheError.value ? 'Could not load employees. Sign in and try again.' : null))

const { confirm } = useConfirm()
const toast = useToast()

const resetPasswordOpen = ref(false)
const resetPasswordTarget = ref<Employee | null>(null)
const generatedPassword = ref('')
const resetPasswordSaving = ref(false)

function generatePassword(length = 14): string {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'
  const bytes = crypto.getRandomValues(new Uint8Array(length))
  return Array.from(bytes, (b) => chars[b % chars.length]).join('')
}

async function openResetPassword(employee: Employee) {
  resetPasswordTarget.value = employee
  generatedPassword.value = generatePassword()
  resetPasswordSaving.value = true
  try {
    await apiFetch(`/api/v1/employees/${employee.id}/password`, {
      method: 'PUT',
      body: { password: generatedPassword.value },
    })
    resetPasswordOpen.value = true
  } catch {
    toast.error('Password reset failed.')
  } finally {
    resetPasswordSaving.value = false
  }
}

async function copyGeneratedPassword() {
  try {
    await navigator.clipboard.writeText(generatedPassword.value)
    toast.success('Password copied.')
  } catch {
    toast.error('Could not copy — select and copy manually.')
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
  } catch {
    toast.error('Update failed.')
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
  } catch {
    toast.error('Revoke failed.')
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
  } catch {
    toast.error('Delete failed.')
  }
}

onMounted(load)
</script>

<template>
  <AppShell title="Employees" :subtitle="`${employees.length} total`">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" @click="refresh">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        Refresh
      </Button>
      <Button size="sm" to="/employees/create">
        <Icon name="plus" class="h-3.5 w-3.5" />
        Add employee
      </Button>
    </template>

    <Table
      :headers="['Employee', 'Phone', 'Shifts', 'Status', 'Device', '']"
      :loading="loading"
      :error="error"
      :is-empty="employees.length === 0"
      empty-message="No employees yet — add one to get started."
    >
      <tr v-for="employee in employees" :key="employee.id" class="group row-h text-ink">
        <td class="px-4">
          <div class="text-[13px] font-medium">{{ employee.name }}</div>
          <div class="text-[11.5px] text-ink-faint">{{ employee.username ?? employee.email ?? '—' }}</div>
        </td>
        <td class="px-4 text-[13px] tabular">{{ employee.phone ?? '—' }}</td>
        <td class="px-4">
          <div v-if="employee.shifts.length" class="flex max-w-56 flex-wrap gap-1">
            <Badge v-for="shift in employee.shifts" :key="shift.id" variant="neutral">
              {{ shift.name }} · {{ shift.start_time.slice(0, 5) }}–{{ shift.end_time.slice(0, 5) }}
            </Badge>
          </div>
          <span v-else class="text-[12.5px] text-ink-faint">No shifts</span>
        </td>
        <td class="px-4">
          <Badge :variant="employee.is_active ? 'success' : 'neutral'">
            {{ employee.is_active ? 'Active' : 'Inactive' }}
          </Badge>
        </td>
        <td class="px-4">
          <template v-if="employee.device">
            <div class="truncate text-[13px]">{{ employee.device.device_name ?? employee.device.device_identifier }}</div>
            <div class="text-[11px] text-ink-faint">
              {{ employee.device.last_seen_at ? new Date(employee.device.last_seen_at).toLocaleDateString() : 'never seen' }}
            </div>
          </template>
          <span v-else class="text-[12.5px] text-ink-faint">—</span>
        </td>
        <td class="px-4">
          <div class="flex items-center justify-end gap-0.5 whitespace-nowrap opacity-100 transition-opacity duration-fast lg:opacity-0 lg:group-hover:opacity-100 lg:focus-within:opacity-100">
            <NuxtLink :to="`/employees/${employee.id}`" class="rounded-sm px-2 py-1.5 text-[12.5px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
              Schedule
            </NuxtLink>
            <NuxtLink :to="`/employees/${employee.id}/histories`" class="rounded-sm px-2 py-1.5 text-[12.5px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
              Histories
            </NuxtLink>
            <button type="button" class="rounded-sm px-2 py-1.5 text-[12.5px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-ink" @click="openResetPassword(employee)">
              Reset password
            </button>
            <button type="button" class="rounded-sm px-2 py-1.5 text-[12.5px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" @click="toggleActive(employee)">
              {{ employee.is_active ? 'Deactivate' : 'Activate' }}
            </button>
            <button v-if="employee.device" type="button" class="rounded-sm px-2 py-1.5 text-[12.5px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" @click="revokeDevice(employee)">
              Revoke device
            </button>
            <button type="button" class="rounded-sm p-1.5 text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" title="Delete employee" aria-label="Delete employee" @click="removeEmployee(employee)">
              <Icon name="trash" class="h-3.5 w-3.5" />
            </button>
          </div>
        </td>
      </tr>
    </Table>

    <Modal v-model="resetPasswordOpen" title="Password reset">
      <p class="mb-3">
        New password for <span class="font-medium text-ink">{{ resetPasswordTarget?.name }}</span>. This is shown
        once — copy it now.
      </p>
      <div class="flex items-center gap-2 rounded-md border border-hairline bg-surface-sunken px-3 py-2">
        <code class="flex-1 select-all font-mono text-[13px] tabular-nums text-ink">{{ generatedPassword }}</code>
        <Button size="sm" variant="secondary" @click="copyGeneratedPassword">Copy</Button>
      </div>
      <template #footer>
        <Button @click="resetPasswordOpen = false">Done</Button>
      </template>
    </Modal>
  </AppShell>
</template>
