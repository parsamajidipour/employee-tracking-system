<script setup lang="ts">
interface Team {
  id: number
  name: string
}

interface Employee {
  id: number
  name: string
  email: string
  team_id: number | null
  team?: Team | null
}

const employees = ref<Employee[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    employees.value = await apiFetch<Employee[]>('/api/v1/employees')
  } catch {
    error.value = 'Not signed in, or failed to load employees.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-8">
    <h1 class="mb-4 text-xl font-semibold text-slate-900">Employees</h1>

    <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>
    <div v-if="loading" class="text-slate-500">Loading…</div>

    <table v-else class="w-full max-w-2xl border-collapse text-left text-sm">
      <thead>
        <tr class="border-b border-slate-300">
          <th class="py-1 pr-4">Name</th>
          <th class="py-1 pr-4">Email</th>
          <th class="py-1 pr-4">Team</th>
          <th class="py-1"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="employee in employees" :key="employee.id" class="border-b border-slate-200">
          <td class="py-1 pr-4">{{ employee.name }}</td>
          <td class="py-1 pr-4">{{ employee.email }}</td>
          <td class="py-1 pr-4">{{ employee.team?.name ?? '—' }}</td>
          <td class="py-1">
            <NuxtLink :to="`/employees/${employee.id}`" class="text-blue-600 hover:underline">Schedule</NuxtLink>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
