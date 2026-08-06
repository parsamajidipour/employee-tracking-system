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
  // No 0/O/1/l/I — an admin often reads this out loud or texts it over.
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

onMounted(load)
</script>

<template>
  <AppShell title="Employees">
    <template #actions>
      <Button to="/employees/create">Add employee</Button>
    </template>

    <Table
      :headers="['Name', 'Username / email', 'Phone', 'Role', 'Active', 'Device', '']"
      :loading="loading"
      :error="error"
      :is-empty="employees.length === 0"
      empty-message="No employees yet — add one to get started."
    >
      <tr v-for="employee in employees" :key="employee.id" class="text-slate-700">
        <td class="px-4 py-3 font-medium text-slate-900">{{ employee.name }}</td>
        <td class="px-4 py-3">{{ employee.username ?? employee.email ?? '—' }}</td>
        <td class="px-4 py-3">{{ employee.phone ?? '—' }}</td>
        <td class="px-4 py-3"><Badge>{{ employee.role }}</Badge></td>
        <td class="px-4 py-3">
          <Badge :variant="employee.is_active ? 'success' : 'neutral'">
            {{ employee.is_active ? 'Active' : 'Inactive' }}
          </Badge>
        </td>
        <td class="px-4 py-3">
          <template v-if="employee.device">
            <div>{{ employee.device.device_name ?? employee.device.device_identifier }}</div>
            <div class="tabular-nums text-xs text-slate-400">
              {{ employee.device.last_seen_at ? new Date(employee.device.last_seen_at).toLocaleString() : 'never seen' }}
            </div>
          </template>
          <span v-else class="text-slate-400">—</span>
        </td>
        <td class="px-4 py-3 text-right whitespace-nowrap">
          <Button size="sm" variant="secondary" :to="`/employees/${employee.id}`">Schedule</Button>
          <Button size="sm" variant="secondary" class="ml-2" @click="openResetPassword(employee)">Reset password</Button>
          <Button size="sm" :variant="employee.is_active ? 'danger' : 'secondary'" class="ml-2" @click="toggleActive(employee)">
            {{ employee.is_active ? 'Deactivate' : 'Activate' }}
          </Button>
          <Button v-if="employee.device" size="sm" variant="danger" class="ml-2" @click="revokeDevice(employee)">
            Revoke
          </Button>
        </td>
      </tr>
    </Table>

    <Modal v-model="resetPasswordOpen" title="Password reset">
      <p class="mb-3">
        New password for <span class="font-medium text-slate-900">{{ resetPasswordTarget?.name }}</span>. This is shown
        once — copy it now.
      </p>
      <div class="flex items-center gap-2 rounded border border-slate-200 bg-slate-50 px-3 py-2">
        <code class="flex-1 select-all font-mono text-sm tabular-nums text-slate-900">{{ generatedPassword }}</code>
        <Button size="sm" variant="secondary" @click="copyGeneratedPassword">Copy</Button>
      </div>
      <template #footer>
        <Button @click="resetPasswordOpen = false">Done</Button>
      </template>
    </Modal>
  </AppShell>
</template>
