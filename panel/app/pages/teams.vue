<script setup lang="ts">
interface Team {
  id: number
  name: string
  timezone: string
}

const teams = ref<Team[]>([])
const loading = ref(true)
const error = ref('')

const editingId = ref<number | null>(null)
const form = reactive({ name: '', timezone: 'Asia/Muscat' })

async function load() {
  loading.value = true
  try {
    teams.value = await apiFetch<Team[]>('/api/v1/teams')
    error.value = ''
  } catch {
    error.value = 'Not signed in, or failed to load teams.'
  } finally {
    loading.value = false
  }
}

function startCreate() {
  editingId.value = null
  form.name = ''
  form.timezone = 'Asia/Muscat'
}

function startEdit(team: Team) {
  editingId.value = team.id
  form.name = team.name
  form.timezone = team.timezone
}

async function submit() {
  error.value = ''
  try {
    if (editingId.value === null) {
      await apiFetch('/api/v1/teams', { method: 'POST', body: { ...form } })
    } else {
      await apiFetch(`/api/v1/teams/${editingId.value}`, { method: 'PUT', body: { ...form } })
    }
    startCreate()
    await load()
  } catch {
    error.value = 'Save failed — check the fields (timezone must be a valid IANA name).'
  }
}

async function remove(team: Team) {
  if (!confirm(`Delete team "${team.name}"?`)) return
  try {
    await apiFetch(`/api/v1/teams/${team.id}`, { method: 'DELETE' })
    await load()
  } catch {
    error.value = 'Delete failed.'
  }
}

onMounted(load)
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-8">
    <h1 class="mb-4 text-xl font-semibold text-slate-900">Teams</h1>

    <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>

    <form @submit.prevent="submit" class="mb-6 flex max-w-lg items-end gap-2">
      <div class="flex-1">
        <label class="block text-xs text-slate-500">Name</label>
        <input v-model="form.name" required class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
      </div>
      <div class="flex-1">
        <label class="block text-xs text-slate-500">Timezone</label>
        <input v-model="form.timezone" required placeholder="Asia/Muscat" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
      </div>
      <button type="submit" class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white">
        {{ editingId === null ? 'Add team' : 'Save' }}
      </button>
      <button v-if="editingId !== null" type="button" @click="startCreate" class="rounded border border-slate-300 px-3 py-1.5 text-sm">
        Cancel
      </button>
    </form>

    <div v-if="loading" class="text-slate-500">Loading…</div>
    <table v-else class="w-full max-w-2xl border-collapse text-left text-sm">
      <thead>
        <tr class="border-b border-slate-300">
          <th class="py-1 pr-4">Name</th>
          <th class="py-1 pr-4">Timezone</th>
          <th class="py-1"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="team in teams" :key="team.id" class="border-b border-slate-200">
          <td class="py-1 pr-4">{{ team.name }}</td>
          <td class="py-1 pr-4">{{ team.timezone }}</td>
          <td class="py-1">
            <button @click="startEdit(team)" class="mr-2 text-blue-600 hover:underline">Edit</button>
            <button @click="remove(team)" class="text-red-600 hover:underline">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
