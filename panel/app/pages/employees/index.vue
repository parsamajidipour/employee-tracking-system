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
const error = ref('')

async function load() {
  loading.value = true
  try {
    employees.value = await apiFetch<Employee[]>('/api/v1/employees')
    error.value = ''
  } catch {
    error.value = 'Not signed in, or failed to load employees.'
  } finally {
    loading.value = false
  }
}

async function resetPassword(employee: Employee) {
  const password = window.prompt(`New password for ${employee.name} (min 8 characters):`)
  if (!password) return
  try {
    await apiFetch(`/api/v1/employees/${employee.id}/password`, { method: 'PUT', body: { password } })
    window.alert('Password reset.')
  } catch {
    error.value = 'Password reset failed — password must be at least 8 characters.'
  }
}

async function toggleActive(employee: Employee) {
  const next = !employee.is_active
  if (!confirm(`${next ? 'Activate' : 'Deactivate'} ${employee.name}?`)) return
  try {
    await apiFetch(`/api/v1/employees/${employee.id}/active`, { method: 'PUT', body: { is_active: next } })
    await load()
  } catch {
    error.value = 'Update failed.'
  }
}

async function revokeDevice(employee: Employee) {
  if (!confirm(`Revoke ${employee.name}'s device? They will need to log in again on a new phone.`)) return
  try {
    await apiFetch(`/api/v1/employees/${employee.id}/device`, { method: 'DELETE' })
    await load()
  } catch {
    error.value = 'Revoke failed.'
  }
}

onMounted(load)
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-8">
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-xl font-semibold text-slate-900">Employees</h1>
      <NuxtLink to="/employees/create" class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white">
        Add employee
      </NuxtLink>
    </div>

    <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>
    <div v-if="loading" class="text-slate-500">Loading…</div>

    <table v-else class="w-full max-w-5xl border-collapse text-left text-sm">
      <thead>
        <tr class="border-b border-slate-300">
          <th class="py-1 pr-4">Name</th>
          <th class="py-1 pr-4">Username / email</th>
          <th class="py-1 pr-4">Phone</th>
          <th class="py-1 pr-4">Role</th>
          <th class="py-1 pr-4">Active</th>
          <th class="py-1 pr-4">Device</th>
          <th class="py-1"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="employee in employees" :key="employee.id" class="border-b border-slate-200">
          <td class="py-1 pr-4">{{ employee.name }}</td>
          <td class="py-1 pr-4">{{ employee.username ?? employee.email ?? '—' }}</td>
          <td class="py-1 pr-4">{{ employee.phone ?? '—' }}</td>
          <td class="py-1 pr-4">{{ employee.role }}</td>
          <td class="py-1 pr-4">{{ employee.is_active ? 'Active' : 'Inactive' }}</td>
          <td class="py-1 pr-4">
            <template v-if="employee.device">
              {{ employee.device.device_name ?? employee.device.device_identifier }}
              <span class="text-slate-400">
                ({{ employee.device.last_seen_at ? new Date(employee.device.last_seen_at).toLocaleString() : 'never seen' }})
              </span>
              <button @click="revokeDevice(employee)" class="ml-2 text-red-600 hover:underline">Revoke</button>
            </template>
            <span v-else class="text-slate-400">—</span>
          </td>
          <td class="py-1 whitespace-nowrap">
            <NuxtLink :to="`/employees/${employee.id}`" class="mr-2 text-blue-600 hover:underline">Schedule</NuxtLink>
            <button @click="resetPassword(employee)" class="mr-2 text-blue-600 hover:underline">Reset password</button>
            <button @click="toggleActive(employee)" class="text-red-600 hover:underline">
              {{ employee.is_active ? 'Deactivate' : 'Activate' }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
