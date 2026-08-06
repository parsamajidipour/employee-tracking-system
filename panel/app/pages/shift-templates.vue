<script setup lang="ts">
interface ShiftTemplate {
  id: number
  team_id: number
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

// This deployment is a single company with one team (see DECISIONS.md) —
// there is no team picker anywhere in the UI. Fetched once so every
// template create/update can still send the team_id the API requires,
// without ever showing it.
const defaultTeamId = ref<number | null>(null)
const templates = ref<ShiftTemplate[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const { confirm } = useConfirm()
const toast = useToast()

const editingId = ref<number | null>(null)
const form = reactive({
  name: '',
  timezone: 'Asia/Muscat',
  days_of_week: [0, 1, 2, 3, 4] as number[],
  start_time: '07:00',
  end_time: '16:00',
  // Strings, not numbers: TextInput's v-model is string-typed (it wraps a
  // native <input>), so these are converted to numbers only at submit
  // time, in submit()'s body.
  grace_before_min: '0',
  grace_after_min: '0',
  max_daily_minutes: '',
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
    const teams = await apiFetch<{ id: number }[]>('/api/v1/teams')
    defaultTeamId.value = teams[0]?.id ?? null
    templates.value = await apiFetch<ShiftTemplate[]>('/api/v1/shift-templates')
    error.value = null
  } catch {
    error.value = 'Could not load shift templates. Sign in and try again.'
  } finally {
    loading.value = false
  }
}

function startCreate() {
  editingId.value = null
  form.name = ''
  form.timezone = 'Asia/Muscat'
  form.days_of_week = [0, 1, 2, 3, 4]
  form.start_time = '07:00'
  form.end_time = '16:00'
  form.grace_before_min = '0'
  form.grace_after_min = '0'
  form.max_daily_minutes = ''
}

function startEdit(template: ShiftTemplate) {
  editingId.value = template.id
  form.name = template.name
  form.timezone = template.timezone
  form.days_of_week = [...template.days_of_week]
  form.start_time = template.start_time.slice(0, 5)
  form.end_time = template.end_time.slice(0, 5)
  form.grace_before_min = String(template.grace_before_min)
  form.grace_after_min = String(template.grace_after_min)
  form.max_daily_minutes = template.max_daily_minutes === null ? '' : String(template.max_daily_minutes)
}

function buildBody() {
  return {
    name: form.name,
    timezone: form.timezone,
    days_of_week: form.days_of_week,
    start_time: form.start_time,
    end_time: form.end_time,
    grace_before_min: Number(form.grace_before_min) || 0,
    grace_after_min: Number(form.grace_after_min) || 0,
    max_daily_minutes: form.max_daily_minutes === '' ? null : Number(form.max_daily_minutes),
    team_id: defaultTeamId.value,
  }
}

async function submit() {
  try {
    if (editingId.value === null) {
      await apiFetch('/api/v1/shift-templates', { method: 'POST', body: buildBody() })
      toast.success('Shift template created.')
    } else {
      await apiFetch(`/api/v1/shift-templates/${editingId.value}`, { method: 'PUT', body: buildBody() })
      toast.success('Shift template saved.')
    }
    startCreate()
    await load()
  } catch {
    toast.error('Save failed — check the fields.')
  }
}

async function remove(template: ShiftTemplate) {
  if (!(await confirm(`Delete shift template "${template.name}"? This cannot be undone.`, { variant: 'danger', title: 'Delete template' }))) return
  try {
    await apiFetch(`/api/v1/shift-templates/${template.id}`, { method: 'DELETE' })
    toast.success('Shift template deleted.')
    await load()
  } catch {
    toast.error('Delete failed.')
  }
}

onMounted(load)
</script>

<template>
  <AppShell title="Shift templates">
    <form @submit.prevent="submit" class="mb-6 max-w-2xl space-y-3 rounded border border-slate-200 bg-white p-4">
      <div class="flex gap-2">
        <TextInput v-model="form.name" label="Name" required class="flex-1" />
        <TextInput v-model="form.timezone" label="Timezone" required class="flex-1" />
      </div>

      <div>
        <span class="mb-1 block text-xs font-medium text-slate-500">Days of week</span>
        <label v-for="(label, day) in DAY_LABELS" :key="day" class="mr-3 text-sm text-slate-700">
          <input type="checkbox" :value="day" v-model="form.days_of_week" class="mr-1 accent-blue-600" />{{ label }}
        </label>
      </div>

      <div class="flex flex-wrap gap-2">
        <TextInput v-model="form.start_time" type="time" label="Start" required class="w-32" />
        <TextInput v-model="form.end_time" type="time" label="End" required class="w-32" />
        <TextInput v-model="form.grace_before_min" type="number" min="0" label="Grace before (min)" class="w-32" />
        <TextInput v-model="form.grace_after_min" type="number" min="0" label="Grace after (min)" class="w-32" />
        <TextInput v-model="form.max_daily_minutes" type="number" min="0" label="Max daily minutes" class="w-36" />
      </div>

      <div class="flex items-center gap-2 pt-1">
        <Button type="submit">{{ editingId === null ? 'Add template' : 'Save' }}</Button>
        <Button v-if="editingId !== null" type="button" variant="secondary" @click="startCreate">Cancel</Button>
      </div>
    </form>

    <Table
      :headers="['Name', 'Days', 'Hours', 'Grace', '']"
      :loading="loading"
      :error="error"
      :is-empty="templates.length === 0"
      empty-message="No shift templates yet — add one above."
    >
      <tr v-for="template in templates" :key="template.id" class="text-slate-700">
        <td class="px-4 py-3 font-medium text-slate-900">{{ template.name }}</td>
        <td class="px-4 py-3">{{ dayLabel(template.days_of_week) }}</td>
        <td class="px-4 py-3">{{ template.start_time.slice(0, 5) }}–{{ template.end_time.slice(0, 5) }}</td>
        <td class="px-4 py-3">{{ template.grace_before_min }}/{{ template.grace_after_min }}</td>
        <td class="px-4 py-3 text-right">
          <Button size="sm" variant="secondary" @click="startEdit(template)">Edit</Button>
          <Button size="sm" variant="danger" class="ml-2" @click="remove(template)">Delete</Button>
        </td>
      </tr>
    </Table>
  </AppShell>
</template>
