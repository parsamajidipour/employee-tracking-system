<script setup lang="ts">
import type { ShiftTemplate } from '~/composables/useShiftTemplates'

interface EmployeeShiftPreview {
  id: number
  employee: { id: number; name: string } | null
}

const { t } = useI18n()
const { number } = useLocalizedFormat()
const dayLabels = useTranslatedArray('shifts.days')
const tableHeaders = useTranslatedArray('shifts.headers')

const { data: templatesData, loading, error: cacheError, load, refresh } = useShiftTemplates()
const templates = computed(() => templatesData.value ?? [])
const error = computed(() => (cacheError.value ? t('shifts.loadFailed') : null))

const { data: employeesData, load: loadEmployees, refresh: refreshEmployees } = useEmployees()

const assignmentCounts = computed(() => {
  const counts = new Map<number, string[]>()
  for (const employee of employeesData.value ?? []) {
    for (const shift of employee.shifts) {
      counts.set(shift.id, [...(counts.get(shift.id) ?? []), employee.name])
    }
  }
  return counts
})

const { confirm } = useConfirm()
const toast = useToast()

const editingId = ref<number | null>(null)
const formSection = ref<HTMLElement | null>(null)
const formError = ref<string | null>(null)
const form = reactive({
  name: '',
  days_of_week: [0, 1, 2, 3, 4] as number[],
  start_time: '07:00',
  end_time: '16:00',
  grace_before_min: '0',
  grace_after_min: '0',
  max_daily_minutes: '',
})

const editingTemplate = computed(() => templates.value.find((template) => template.id === editingId.value) ?? null)

const crossesMidnight = computed(() => form.end_time <= form.start_time)

function dayLabel(days: number[]): string {
  if (days.length === 0) return t('shifts.noDays')
  if (days.length === 7) return t('shifts.everyDay')

  return days
    .slice()
    .sort((a, b) => a - b)
    .map((d) => dayLabels.value[d])
    .join(', ')
}

function assignedNames(templateId: number): string[] {
  return assignmentCounts.value.get(templateId) ?? []
}

function startCreate() {
  editingId.value = null
  formError.value = null
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
  formError.value = null
  form.name = template.name
  form.days_of_week = [...template.days_of_week]
  form.start_time = template.start_time.slice(0, 5)
  form.end_time = template.end_time.slice(0, 5)
  form.grace_before_min = String(template.grace_before_min)
  form.grace_after_min = String(template.grace_after_min)
  form.max_daily_minutes = template.max_daily_minutes === null ? '' : String(template.max_daily_minutes)

  nextTick(() => {
    if (!window.matchMedia('(max-width: 1023px)').matches) return
    formSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    window.setTimeout(() => {
      formSection.value?.querySelector<HTMLInputElement>('input')?.focus({ preventScroll: true })
    }, 250)
  })
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

const submitting = ref(false)

async function submit() {
  if (form.name.trim() === '') {
    formError.value = t('shifts.nameRequired')
    return
  }
  if (form.days_of_week.length === 0) {
    formError.value = t('shifts.dayRequired')
    return
  }

  formError.value = null
  submitting.value = true
  try {
    if (editingId.value === null) {
      await apiFetch('/api/v1/shift-templates', { method: 'POST', body: buildBody() })
      toast.success(t('shifts.created'))
    } else {
      await apiFetch(`/api/v1/shift-templates/${editingId.value}`, { method: 'PUT', body: buildBody() })
      toast.success(t('shifts.saved'))
    }
    startCreate()
    await refresh()
    await refreshEmployees()
  } catch (err) {
    formError.value = apiErrorMessage(err, t('shifts.saveFailed'))
    toast.error(formError.value)
  } finally {
    submitting.value = false
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
    ? t('shifts.deleteAffected', { name: template.name, count: number(names.length), employees: names.join(', ') })
    : t('shifts.deleteConfirm', { name: template.name })

  if (!(await confirm(message, { variant: 'danger', title: t('shifts.deleteTitle') }))) return

  try {
    await apiFetch(`/api/v1/shift-templates/${template.id}`, { method: 'DELETE' })
    toast.success(t('shifts.deleted'))
    if (editingId.value === template.id) startCreate()
    await refresh()
    await refreshEmployees()
  } catch (err) {
    toast.error(apiErrorMessage(err, t('shifts.deleteFailed')))
  }
}

function toggleDay(day: number) {
  form.days_of_week = form.days_of_week.includes(day)
    ? form.days_of_week.filter((value) => value !== day)
    : [...form.days_of_week, day]
}

function refreshAll() {
  refresh()
  refreshEmployees()
}

onMounted(() => {
  load()
  loadEmployees()
})
</script>

<template>
  <AppShell :title="t('shifts.title')" :subtitle="t('shifts.subtitle')" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" :aria-label="t('shifts.refresh')" @click="refreshAll">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">{{ t('common.refresh') }}</span>
      </Button>
    </template>

    <div class="flex h-full min-h-0 flex-col gap-3 overflow-y-auto p-3 sm:gap-4 sm:p-5 lg:grid lg:grid-cols-[minmax(0,400px)_minmax(0,1fr)] lg:overflow-hidden">
      <div ref="formSection" class="flex-none scroll-mt-3 lg:min-h-0 lg:overflow-y-auto lg:pe-1">
        <Card
          :icon="editingId === null ? 'plus' : 'pencil'"
          :title="editingId === null ? t('shifts.new') : t('shifts.edit')"
          :subtitle="editingId === null ? t('shifts.newSubtitle') : editingTemplate?.name"
        >
          <form class="space-y-4" @submit.prevent="submit">
            <InlineAlert v-if="formError" class="!mb-0">{{ formError }}</InlineAlert>

            <TextInput v-model="form.name" :label="t('common.name')" :placeholder="t('shifts.namePlaceholder')" required />

            <div>
              <span class="mb-1.5 block text-[12px] font-medium text-ink-soft">{{ t('shifts.daysOfWeek') }}</span>
              <div class="flex gap-1.5 overflow-x-auto pb-1 min-[420px]:grid min-[420px]:grid-cols-7 min-[420px]:overflow-visible min-[420px]:pb-0">
                <button
                  v-for="(label, day) in dayLabels"
                  :key="day"
                  type="button"
                  class="h-10 w-10 flex-none rounded-md border px-0 text-[12.5px] font-medium transition-colors duration-fast ease-soft min-[420px]:w-auto"
                  :class="form.days_of_week.includes(Number(day))
                    ? 'border-primary bg-primary-soft text-primary-strong'
                    : 'border-hairline bg-surface text-ink-soft hover:border-primary/50 hover:text-ink'"
                  :aria-pressed="form.days_of_week.includes(Number(day))"
                  @click="toggleDay(Number(day))"
                >
                  {{ label }}
                </button>
              </div>
            </div>

            <section class="border-t border-hairline pt-4">
              <p class="eyebrow mb-2.5">{{ t('shifts.window') }}</p>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <TimeInput v-model="form.start_time" :label="t('shifts.start')" placeholder="07:00" required />
                <TimeInput v-model="form.end_time" :label="t('shifts.end')" placeholder="16:00" required />
              </div>
              <p v-if="crossesMidnight" class="mt-2 text-[12px] text-ink-soft">
                {{ t('shifts.crossesMidnight') }}
              </p>
            </section>

            <section class="border-t border-hairline pt-4">
              <p class="eyebrow mb-2.5">{{ t('shifts.graceCap') }}</p>
              <div class="grid grid-cols-1 gap-3 min-[360px]:grid-cols-2">
                <TextInput
                  v-model="form.grace_before_min"
                  type="number"
                  min="0"
                  :label="t('shifts.graceBefore')"
                  placeholder="0"
                />
                <TextInput
                  v-model="form.grace_after_min"
                  type="number"
                  min="0"
                  :label="t('shifts.graceAfter')"
                  placeholder="0"
                />
              </div>
              <div class="mt-3">
                <TextInput
                  v-model="form.max_daily_minutes"
                  type="number"
                  min="0"
                  :label="t('shifts.maxDaily')"
                  :placeholder="t('shifts.noCap')"
                  :hint="t('shifts.capHint')"
                />
              </div>
            </section>

            <div class="flex flex-col gap-2 border-t border-hairline pt-4 sm:flex-row sm:flex-wrap sm:items-center">
              <Button type="submit" class="w-full sm:w-auto" :loading="submitting">
                {{ editingId === null ? t('shifts.add') : t('shifts.saveChanges') }}
              </Button>
              <Button v-if="editingId !== null" type="button" variant="secondary" :disabled="submitting" @click="startCreate">
                {{ t('shifts.cancelEdit') }}
              </Button>
            </div>
          </form>
        </Card>
      </div>

      <Card
        class="flex-none lg:min-h-0"
        icon="calendar"
        :title="t('shifts.templates')"
        :subtitle="t('shifts.defined', { count: number(templates.length) })"
        flush
      >
        <div class="h-full min-h-0 overflow-y-auto">
          <Table
            embedded
            :headers="tableHeaders"
            :loading="loading"
            :error="error"
            :is-empty="templates.length === 0"
            :empty-message="t('shifts.empty')"
          >
            <template #cards>
              <div v-for="template in templates" :key="template.id" class="surface-flat space-y-3 p-3.5 sm:p-4">
                <div class="flex items-start justify-between gap-3">
                  <p class="text-[14px] font-medium text-ink">{{ template.name }}</p>
                  <Badge :variant="assignedNames(template.id).length ? 'success' : 'neutral'">
                    {{ t('shifts.assigned', { count: number(assignedNames(template.id).length) }) }}
                  </Badge>
                </div>

                <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-[13px]">
                  <div class="col-span-2">
                    <dt class="eyebrow mb-1">{{ t('shifts.daysOfWeek') }}</dt>
                    <dd class="text-ink-soft">{{ dayLabel(template.days_of_week) }}</dd>
                  </div>
                  <div>
                    <dt class="eyebrow mb-1">{{ t('shifts.hours') }}</dt>
                    <dd class="tabular text-ink">{{ template.start_time.slice(0, 5) }}–{{ template.end_time.slice(0, 5) }}</dd>
                  </div>
                  <div>
                    <dt class="eyebrow mb-1">{{ t('shifts.grace') }}</dt>
                    <dd class="tabular text-ink-soft">{{ t('shifts.minutes', { before: number(template.grace_before_min), after: number(template.grace_after_min) }) }}</dd>
                  </div>
                </dl>

                <div class="flex items-center gap-1 border-t border-hairline pt-2.5">
                  <button type="button" class="min-h-10 rounded-sm px-3 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken" @click="startEdit(template)">
                    {{ t('common.edit') }}
                  </button>
                  <button type="button" class="min-h-10 rounded-sm px-3 py-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" @click="remove(template)">
                    {{ t('common.delete') }}
                  </button>
                </div>
              </div>
            </template>

            <tr
              v-for="template in templates"
              :key="template.id"
              class="group row-h text-ink transition-colors hover:bg-surface-sunken/60"
              :class="editingId === template.id ? 'bg-primary-soft/60' : ''"
            >
              <td class="px-4 text-[14px] font-medium sm:px-5">{{ template.name }}</td>
              <td class="px-4 text-[13.5px] text-ink-soft sm:px-5">{{ dayLabel(template.days_of_week) }}</td>
              <td class="px-4 text-[14px] tabular sm:px-5">{{ template.start_time.slice(0, 5) }}–{{ template.end_time.slice(0, 5) }}</td>
              <td class="px-4 text-[14px] tabular text-ink-soft sm:px-5">{{ template.grace_before_min }}/{{ template.grace_after_min }}</td>
              <td class="px-4 sm:px-5">
                <Badge :variant="assignedNames(template.id).length ? 'success' : 'neutral'">
                  {{ t('shifts.employees', { count: number(assignedNames(template.id).length) }) }}
                </Badge>
              </td>
              <td class="px-4 sm:px-5">
                <div class="flex items-center justify-end gap-1">
                  <button
                    type="button"
                    class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken"
                    @click="startEdit(template)"
                  >
                    {{ t('common.edit') }}
                  </button>
                  <button
                    type="button"
                    class="rounded-sm px-2.5 py-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger"
                    @click="remove(template)"
                  >
                    {{ t('common.delete') }}
                  </button>
                </div>
              </td>
            </tr>
          </Table>
        </div>
      </Card>
    </div>
  </AppShell>
</template>
