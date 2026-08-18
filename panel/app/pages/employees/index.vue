<script setup lang="ts">
interface Device {
  device_identifier: string
  device_name: string | null
  last_seen_at: string | null
}

interface Employee {
  id: number
  name: string
  phone: string | null
  email: string | null
  username: string | null
  role: 'admin' | 'hr' | 'supervisor' | 'employee'
  is_active: boolean
  device: Device | null
  shifts: Array<{ id: number; name: string; start_time: string; end_time: string }>
}

const employees = ref<Employee[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const { confirm } = useConfirm()
const toast = useToast()

const resetPasswordOpen = ref(false)
const resetPasswordTarget = ref<Employee | null>(null)
const generatedPassword = ref('')
const resetPasswordSaving = ref(false)

async function load() {
  loading.value = true
  try {
    employees.value = await apiFetch<Employee[]>('/api/v1/employees')
    error.value = null
  } catch {
    error.value = 'Could not load employees. Sign in and try again.'
  } finally {
    loading.value = false
  }
}

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
    await load()
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
    await load()
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
    await load()
  } catch {
    toast.error('Delete failed.')
  }
}

onMounted(load)
</script>

<template>
  <AppShell title="Employees">
    <template #actions>
      <Button to="/employees/create">Add employee</Button>
    </template>

    <Table
      :headers="['Employee', 'Phone', 'Shifts', 'Status', 'Device', '']"
      :loading="loading"
      :error="error"
      :is-empty="employees.length === 0"
      empty-message="No employees yet — add one to get started."
    >
      <tr v-for="employee in employees" :key="employee.id" class="text-ink">
        <td class="px-5 py-3">
          <div class="font-medium">{{ employee.name }}</div>
          <div class="text-xs text-ink-faint">{{ employee.username ?? employee.email ?? '—' }}</div>
        </td>
        <td class="px-5 py-3">{{ employee.phone ?? '—' }}</td>
        <td class="px-5 py-3">
          <div v-if="employee.shifts.length" class="flex max-w-56 flex-wrap gap-1">
            <Badge v-for="shift in employee.shifts" :key="shift.id" variant="neutral">
              {{ shift.name }} · {{ shift.start_time.slice(0, 5) }}–{{ shift.end_time.slice(0, 5) }}
            </Badge>
          </div>
          <span v-else class="text-ink-faint">No shifts</span>
        </td>
        <td class="px-5 py-3">
          <Badge :variant="employee.is_active ? 'success' : 'neutral'">
            {{ employee.is_active ? 'Active' : 'Inactive' }}
          </Badge>
        </td>
        <td class="px-5 py-3">
          <template v-if="employee.device">
            <div class="truncate">{{ employee.device.device_name ?? employee.device.device_identifier }}</div>
            <div class="text-xs text-ink-faint">
              {{ employee.device.last_seen_at ? new Date(employee.device.last_seen_at).toLocaleDateString() : 'never seen' }}
            </div>
          </template>
          <span v-else class="text-ink-faint">—</span>
        </td>
        <td class="px-5 py-3">
          <div class="flex items-center justify-end gap-1 whitespace-nowrap">
            <NuxtLink
              :to="`/employees/${employee.id}`"
              class="rounded-small px-2 py-1.5 text-sm font-medium text-primary-strong transition-colors hover:bg-surface-muted"
            >
              Schedule
            </NuxtLink>
            <NuxtLink
              :to="`/employees/${employee.id}/histories`"
              class="rounded-small px-2 py-1.5 text-sm font-medium text-primary-strong transition-colors hover:bg-surface-muted"
            >
              Histories
            </NuxtLink>
            <button
              type="button"
              class="rounded-small px-2 py-1.5 text-sm text-ink-soft transition-colors hover:bg-surface-muted hover:text-ink"
              @click="openResetPassword(employee)"
            >
              Reset password
            </button>
            <button
              type="button"
              class="rounded-small px-2 py-1.5 text-sm text-ink-soft transition-colors hover:bg-surface-muted hover:text-state-danger"
              @click="toggleActive(employee)"
            >
              {{ employee.is_active ? 'Deactivate' : 'Activate' }}
            </button>
            <button
              v-if="employee.device"
              type="button"
              class="rounded-small px-2 py-1.5 text-sm text-ink-soft transition-colors hover:bg-surface-muted hover:text-state-danger"
              @click="revokeDevice(employee)"
            >
              Revoke device
            </button>
            <button
              type="button"
              class="rounded-small p-1.5 text-ink-soft transition-colors hover:bg-surface-muted hover:text-state-danger"
              title="Delete employee"
              aria-label="Delete employee"
              @click="removeEmployee(employee)"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                <path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-8 0 1 13a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-13" />
              </svg>
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
      <div class="flex items-center gap-2 rounded-control border border-hairline bg-surface-muted px-3 py-2">
        <code class="flex-1 select-all font-mono text-sm tabular-nums text-ink">{{ generatedPassword }}</code>
        <Button size="sm" variant="secondary" @click="copyGeneratedPassword">Copy</Button>
      </div>
      <template #footer>
        <Button @click="resetPasswordOpen = false">Done</Button>
      </template>
    </Modal>
  </AppShell>
</template>
