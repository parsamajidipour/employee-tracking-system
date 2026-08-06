<script setup lang="ts">
interface Team {
  id: number
  name: string
}

interface ShiftTemplate {
  id: number
  team_id: number
  team?: Team
  name: string
  timezone: string
  days_of_week: number[]
  start_time: string
  end_time: string
  grace_before_min: number
  grace_after_min: number
  max_daily_minutes: number | null
}

const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const teams = ref<Team[]>([])
const templates = ref<ShiftTemplate[]>([])
const loading = ref(true)
const error = ref('')

const editingId = ref<number | null>(null)
const form = reactive({
  team_id: null as number | null,
  name: '',
  timezone: 'Asia/Muscat',
  days_of_week: [0, 1, 2, 3, 4] as number[],
  start_time: '07:00',
  end_time: '16:00',
  grace_before_min: 0,
  grace_after_min: 0,
  max_daily_minutes: null as number | null,
})

function dayLabel(days: number[]): string {
  return days
    .slice()
    .sort((a, b) => a - b)
    .map((d) => DAY_LABELS[d])
    .join(', ')
}

async function load() {
  loading.value = true
  try {
    teams.value = await apiFetch<Team[]>('/api/v1/teams')
    templates.value = await apiFetch<ShiftTemplate[]>('/api/v1/shift-templates')
    error.value = ''
  } catch {
    error.value = 'Not signed in, or failed to load shift templates.'
  } finally {
    loading.value = false
  }
}

function startCreate() {
  editingId.value = null
  form.team_id = teams.value[0]?.id ?? null
  form.name = ''
  form.timezone = 'Asia/Muscat'
  form.days_of_week = [0, 1, 2, 3, 4]
  form.start_time = '07:00'
  form.end_time = '16:00'
  form.grace_before_min = 0
  form.grace_after_min = 0
  form.max_daily_minutes = null
}

function startEdit(template: ShiftTemplate) {
  editingId.value = template.id
  form.team_id = template.team_id
  form.name = template.name
  form.timezone = template.timezone
  form.days_of_week = [...template.days_of_week]
  form.start_time = template.start_time.slice(0, 5)
  form.end_time = template.end_time.slice(0, 5)
  form.grace_before_min = template.grace_before_min
  form.grace_after_min = template.grace_after_min
  form.max_daily_minutes = template.max_daily_minutes
}

async function submit() {
  error.value = ''
  try {
    if (editingId.value === null) {
      await apiFetch('/api/v1/shift-templates', { method: 'POST', body: { ...form } })
    } else {
      await apiFetch(`/api/v1/shift-templates/${editingId.value}`, { method: 'PUT', body: { ...form } })
    }
    startCreate()
    await load()
  } catch {
    error.value = 'Save failed — check the fields.'
  }
}

async function remove(template: ShiftTemplate) {
  if (!confirm(`Delete shift template "${template.name}"?`)) return
  try {
    await apiFetch(`/api/v1/shift-templates/${template.id}`, { method: 'DELETE' })
    await load()
  } catch {
    error.value = 'Delete failed.'
  }
}

onMounted(load)
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-8">
    <h1 class="mb-4 text-xl font-semibold text-slate-900">Shift templates</h1>

    <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>

    <form @submit.prevent="submit" class="mb-6 max-w-2xl space-y-3 rounded border border-slate-200 bg-white p-4">
      <div class="flex gap-2">
        <div class="flex-1">
          <label class="block text-xs text-slate-500">Team</label>
          <select v-model.number="form.team_id" required class="w-full rounded border border-slate-300 px-2 py-1 text-sm">
            <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
          </select>
        </div>
        <div class="flex-1">
          <label class="block text-xs text-slate-500">Name</label>
          <input v-model="form.name" required class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
        </div>
        <div class="flex-1">
          <label class="block text-xs text-slate-500">Timezone</label>
          <input v-model="form.timezone" required class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
        </div>
      </div>

      <div>
        <label class="block text-xs text-slate-500">Days of week</label>
        <label v-for="(label, day) in DAY_LABELS" :key="day" class="mr-3 text-sm">
          <input type="checkbox" :value="day" v-model="form.days_of_week" class="mr-1" />{{ label }}
        </label>
      </div>

      <div class="flex gap-2">
        <div>
          <label class="block text-xs text-slate-500">Start</label>
          <input v-model="form.start_time" type="time" required class="rounded border border-slate-300 px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="block text-xs text-slate-500">End</label>
          <input v-model="form.end_time" type="time" required class="rounded border border-slate-300 px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="block text-xs text-slate-500">Grace before (min)</label>
          <input v-model.number="form.grace_before_min" type="number" min="0" class="w-24 rounded border border-slate-300 px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="block text-xs text-slate-500">Grace after (min)</label>
          <input v-model.number="form.grace_after_min" type="number" min="0" class="w-24 rounded border border-slate-300 px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="block text-xs text-slate-500">Max daily minutes</label>
          <input v-model.number="form.max_daily_minutes" type="number" min="0" class="w-28 rounded border border-slate-300 px-2 py-1 text-sm" />
        </div>
      </div>

      <div>
        <button type="submit" class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white">
          {{ editingId === null ? 'Add template' : 'Save' }}
        </button>
        <button v-if="editingId !== null" type="button" @click="startCreate" class="ml-2 rounded border border-slate-300 px-3 py-1.5 text-sm">
          Cancel
        </button>
      </div>
    </form>

    <div v-if="loading" class="text-slate-500">Loading…</div>
    <table v-else class="w-full max-w-4xl border-collapse text-left text-sm">
      <thead>
        <tr class="border-b border-slate-300">
          <th class="py-1 pr-4">Team</th>
          <th class="py-1 pr-4">Name</th>
          <th class="py-1 pr-4">Days</th>
          <th class="py-1 pr-4">Hours</th>
          <th class="py-1 pr-4">Grace</th>
          <th class="py-1"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="template in templates" :key="template.id" class="border-b border-slate-200">
          <td class="py-1 pr-4">{{ template.team?.name }}</td>
          <td class="py-1 pr-4">{{ template.name }}</td>
          <td class="py-1 pr-4">{{ dayLabel(template.days_of_week) }}</td>
          <td class="py-1 pr-4">{{ template.start_time.slice(0, 5) }}–{{ template.end_time.slice(0, 5) }}</td>
          <td class="py-1 pr-4">{{ template.grace_before_min }}/{{ template.grace_after_min }}</td>
          <td class="py-1">
            <button @click="startEdit(template)" class="mr-2 text-blue-600 hover:underline">Edit</button>
            <button @click="remove(template)" class="text-red-600 hover:underline">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
