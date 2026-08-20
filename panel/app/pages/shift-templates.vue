<script setup lang="ts">
import type { ShiftTemplate } from '~/composables/useShiftTemplates'

interface EmployeeShiftPreview {
  id: number
  employee: { id: number; name: string } | null
}

const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const { data: templatesData, loading, error: cacheError, load, refresh } = useShiftTemplates()
const templates = computed(() => templatesData.value ?? [])
const error = computed(() => (cacheError.value ? 'Could not load shift templates. Sign in and try again.' : null))

const { confirm } = useConfirm()
const toast = useToast()

const editingId = ref<number | null>(null)
const form = reactive({
  name: '',
  days_of_week: [0, 1, 2, 3, 4] as number[],
  start_time: '07:00',
  end_time: '16:00',

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

function startCreate() {
  editingId.value = null
  form.name = ''
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
    days_of_week: form.days_of_week,
    start_time: form.start_time,
    end_time: form.end_time,
    grace_before_min: Number(form.grace_before_min) || 0,
    grace_after_min: Number(form.grace_after_min) || 0,
    max_daily_minutes: form.max_daily_minutes === '' ? null : Number(form.max_daily_minutes),
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
    await refresh()
  } catch (err) {
    toast.error(apiErrorMessage(err, 'Save failed — check the fields.'))
  }
}

async function remove(template: ShiftTemplate) {
  let affected: EmployeeShiftPreview[] = []
  try {
    affected = await apiFetch<EmployeeShiftPreview[]>(`/api/v1/employee-shifts?template_id=${template.id}`)
  } catch {
    affected = []
  }

  const names = affected.map((shift) => shift.employee?.name).filter((name): name is string => !!name)
  const message = names.length > 0
    ? `Delete shift template "${template.name}"? ${names.length} employee${names.length > 1 ? 's' : ''} will lose this shift and become unscheduled: ${names.join(', ')}.`
    : `Delete shift template "${template.name}"? This cannot be undone.`

  if (!(await confirm(message, { variant: 'danger', title: 'Delete template' }))) return

  try {
    await apiFetch(`/api/v1/shift-templates/${template.id}`, { method: 'DELETE' })
    toast.success('Shift template deleted.')
    await refresh()
  } catch (err) {
    toast.error(apiErrorMessage(err, 'Delete failed.'))
  }
}

onMounted(load)
</script>

<template>
  <AppShell title="Shift templates">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" @click="refresh">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        Refresh
      </Button>
    </template>

    <form @submit.prevent="submit" class="surface-flat mb-5 space-y-4 p-4">
      <h2>{{ editingId === null ? 'New template' : 'Edit template' }}</h2>

      <TextInput v-model="form.name" label="Name" required class="max-w-sm" />

      <div>
        <span class="mb-1.5 block text-[12.5px] font-medium text-ink-soft">Days of week</span>
        <div class="flex flex-wrap gap-1.5">
          <label
            v-for="(label, day) in DAY_LABELS"
            :key="day"
            class="flex h-9 cursor-pointer items-center gap-1.5 rounded-md border border-hairline px-3 text-[13px] transition-colors hover:bg-surface-sunken"
            :class="form.days_of_week.includes(Number(day)) ? '!border-primary bg-primary-soft text-primary-strong' : ''"
          >
            <input type="checkbox" :value="day" v-model="form.days_of_week" class="accent-primary" />
            {{ label }}
          </label>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-5">
        <TextInput v-model="form.start_time" type="time" label="Start" required />
        <TextInput v-model="form.end_time" type="time" label="End" required />
        <TextInput v-model="form.grace_before_min" type="number" min="0" label="Grace before (min)" />
        <TextInput v-model="form.grace_after_min" type="number" min="0" label="Grace after (min)" />
        <TextInput v-model="form.max_daily_minutes" type="number" min="0" label="Max daily minutes" />
      </div>

      <div class="flex items-center gap-2">
        <Button type="submit">{{ editingId === null ? 'Add template' : 'Save changes' }}</Button>
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
      <tr v-for="template in templates" :key="template.id" class="row-h text-ink">
        <td class="px-5 text-[13.5px] font-medium">{{ template.name }}</td>
        <td class="px-5 text-[13.5px] text-ink-soft">{{ dayLabel(template.days_of_week) }}</td>
        <td class="px-5 text-[13.5px] tabular">{{ template.start_time.slice(0, 5) }}–{{ template.end_time.slice(0, 5) }}</td>
        <td class="px-5 text-[13.5px] tabular text-ink-soft">{{ template.grace_before_min }}/{{ template.grace_after_min }}</td>
        <td class="px-5">
          <div class="flex items-center justify-end gap-1">
            <button type="button" class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken" @click="startEdit(template)">
              Edit
            </button>
            <button type="button" class="rounded-sm px-2.5 py-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" @click="remove(template)">
              Delete
            </button>
          </div>
        </td>
      </tr>
    </Table>
  </AppShell>
</template>
