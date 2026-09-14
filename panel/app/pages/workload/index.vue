<script setup lang="ts">
import type { WorkloadDetail, WorkloadActivity, WorkloadRow } from '~/composables/useWorkload'

definePageMeta({ middleware: 'employees-workload' })

const { t } = useI18n()
const { number: formatNumber, date: formatDate, distance: formatDistance } = useLocalizedFormat()

type SortKey = 'name' | 'overdue' | 'active' | 'completed'

function todayLocalDate(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

const { data: rows, loading, error, load } = useWorkloadList()

const { connected } = useCaseStream(() => {
  load()
})

const search = ref('')
const sortKey = ref<SortKey>('overdue')
const advancedFilterCount = computed(() => Number(sortKey.value !== 'overdue'))

const totals = computed(() =>
  rows.value.reduce(
    (acc, row) => {
      acc.active += row.summary.active_cases
      acc.overdue += row.summary.overdue
      acc.completedToday += row.summary.completed_today
      return acc
    },
    { active: 0, overdue: 0, completedToday: 0 },
  ),
)

const visibleRows = computed(() => {
  const term = search.value.trim().toLowerCase()
  const filtered = term ? rows.value.filter((row) => row.name.toLowerCase().includes(term)) : [...rows.value]

  return filtered.sort((a, b) => {
    if (sortKey.value === 'name') return a.name.localeCompare(b.name)
    if (sortKey.value === 'active') return b.summary.active_cases - a.summary.active_cases || a.name.localeCompare(b.name)
    if (sortKey.value === 'completed') return b.summary.completed_week - a.summary.completed_week || a.name.localeCompare(b.name)
    return b.summary.overdue - a.summary.overdue || b.summary.active_cases - a.summary.active_cases
  })
})

const drawerOpen = ref(false)
const drawerEmployeeId = ref<number | null>(null)
const drawerEmployeeName = ref('')
const drawerDate = ref(todayLocalDate())
const drawerDetail = ref<WorkloadDetail | null>(null)
const drawerLoading = ref(false)
const drawerError = ref<string | null>(null)

function minutesLabel(minutes: number): string {
  const h = Math.floor(minutes / 60)
  const m = Math.round(minutes % 60)
  if (h === 0) return t('workload.durationMinutes', { minutes: formatNumber(m) })
  return t('workload.durationHoursMinutes', { hours: formatNumber(h), minutes: formatNumber(m) })
}

function segments(activity: WorkloadActivity) {
  const total = activity.inspection_minutes + activity.travel_minutes + activity.idle_minutes
  if (total <= 0) return { inspection: 0, travel: 0, idle: 100 }
  return {
    inspection: (activity.inspection_minutes / total) * 100,
    travel: (activity.travel_minutes / total) * 100,
    idle: (activity.idle_minutes / total) * 100,
  }
}

function utilizationPct(activity: WorkloadActivity): number {
  const total = activity.inspection_minutes + activity.travel_minutes + activity.idle_minutes
  if (total <= 0) return 0
  return Math.round(((activity.inspection_minutes + activity.travel_minutes) / total) * 100)
}

function ringStyle(activity: WorkloadActivity) {
  const s = segments(activity)
  const a = s.inspection
  const b = a + s.travel
  return {
    background: `conic-gradient(var(--primary) 0% ${a}%, var(--warning) ${a}% ${b}%, var(--neutral-soft) ${b}% 100%)`,
  }
}

function statusOf(row: WorkloadRow): { label: string; variant: 'success' | 'warning' | 'danger' | 'neutral' } {
  if (row.summary.overdue > 0) return { label: t('workload.overdueCount', { count: formatNumber(row.summary.overdue) }), variant: 'danger' }
  if (row.summary.active_cases === 0) return { label: t('workload.noOpenWork'), variant: 'neutral' }
  return { label: t('workload.onTrack'), variant: 'success' }
}

async function loadDrawerDetail() {
  if (drawerEmployeeId.value === null) return
  drawerLoading.value = true
  try {
    drawerDetail.value = await fetchWorkloadDetail(drawerEmployeeId.value, drawerDate.value)
    drawerError.value = null
  } catch (err) {
    drawerError.value = apiErrorMessage(err, t('workload.loadDayFailed'))
  } finally {
    drawerLoading.value = false
  }
}

function openDrawer(employeeId: number, name: string) {
  drawerEmployeeId.value = employeeId
  drawerEmployeeName.value = name
  drawerDate.value = todayLocalDate()
  drawerDetail.value = null
  drawerOpen.value = true
  loadDrawerDetail()
}

watch(drawerDate, () => {
  if (drawerOpen.value) loadDrawerDetail()
})

onMounted(load)
</script>

<template>
  <AppShell :title="t('workload.title')" :subtitle="t('workload.subtitle')" full-bleed>
    <template #actions>
      <span
        class="hidden items-center gap-1.5 rounded-pill bg-surface-sunken px-2.5 py-1.5 text-[11.5px] font-semibold text-ink-soft sm:inline-flex"
        :title="connected ? t('workload.liveTitle') : t('workload.offlineTitle')"
      >
        <span class="h-1.5 w-1.5 rounded-full" :class="connected ? 'bg-state-success' : 'bg-state-neutral'"></span>
        {{ connected ? t('common.live') : t('common.offline') }}
      </span>
      <Button variant="secondary" size="sm" :disabled="loading" :aria-label="t('workload.refresh')" @click="load">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">{{ t('common.refresh') }}</span>
      </Button>
    </template>

    <div class="flex h-full min-h-0 flex-col gap-4 overflow-y-auto p-3 sm:p-5 lg:overflow-hidden">
      <InlineAlert v-if="error" class="!mb-0 flex-none">{{ error }}</InlineAlert>

      <div class="grid flex-none grid-cols-2 gap-2.5 lg:grid-cols-4">
        <StatCard icon="users" :label="t('workload.surveyorsTracked')" :value="formatNumber(rows.length)" accent="neutral" />
        <StatCard icon="briefcase" :label="t('workload.openOrg')" :value="formatNumber(totals.active)" accent="primary" />
        <StatCard
          icon="alert-triangle"
          :label="t('workload.overdueOrg')"
          :value="formatNumber(totals.overdue)"
          :accent="totals.overdue > 0 ? 'danger' : 'neutral'"
        />
        <StatCard icon="check-circle" :label="t('workload.completedToday')" :value="formatNumber(totals.completedToday)" accent="success" />
      </div>

      <Card
        class="flex-none lg:min-h-0 lg:flex-1"
        icon="chart-bar"
        :title="t('workload.bySurveyor')"
        :subtitle="t('workload.shown', { shown: formatNumber(visibleRows.length), total: formatNumber(rows.length) })"
        flush
      >
        <div class="flex min-h-0 flex-col lg:h-full">
          <div class="flex flex-none flex-col gap-3 border-b border-hairline bg-surface-sunken/60 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-end sm:px-5">
            <div class="w-full min-w-0 sm:min-w-56 sm:flex-1">
              <TextInput v-model="search" :label="t('common.search')" icon="search" :placeholder="t('workload.searchPlaceholder')" />
            </div>
            <ResponsiveFilterGroup :title="t('common.moreFilters')" :active-count="advancedFilterCount">
              <div class="w-full sm:w-52">
                <Select v-model="sortKey" :label="t('workload.sortBy')">
                  <option value="overdue">{{ t('workload.sortOverdue') }}</option>
                  <option value="active">{{ t('workload.sortOpen') }}</option>
                  <option value="completed">{{ t('workload.sortCompleted') }}</option>
                  <option value="name">{{ t('workload.sortName') }}</option>
                </Select>
              </div>
              <template #footer>
                <Button
                  v-if="advancedFilterCount > 0"
                  variant="ghost"
                  size="sm"
                  class="w-full justify-center sm:w-auto"
                  @click="sortKey = 'overdue'"
                >
                  <Icon name="close" class="h-3.5 w-3.5" />
                  {{ t('common.clearFilters') }}
                </Button>
              </template>
            </ResponsiveFilterGroup>
          </div>

          <div class="min-h-0 lg:flex-1 lg:overflow-y-auto">
            <div v-if="loading && rows.length === 0" class="space-y-2.5 p-4 sm:p-5">
              <Skeleton v-for="i in 5" :key="i" class="h-16" rounded="md" />
            </div>

            <EmptyState
              v-else-if="visibleRows.length === 0"
              icon="briefcase"
              :message="rows.length === 0 ? t('workload.empty') : t('workload.emptySearch')"
              class="py-12"
            />

            <ul v-else class="divide-y divide-hairline">
              <li v-for="row in visibleRows" :key="row.employee_id">
                <button
                  type="button"
                  class="flex w-full flex-col gap-3.5 px-4 py-3.5 text-start transition-colors duration-fast ease-soft hover:bg-surface-sunken/60 sm:px-5 xl:flex-row xl:items-center xl:gap-5"
                  @click="openDrawer(row.employee_id, row.name)"
                >
                  <span class="flex min-w-0 flex-1 items-center gap-3">
                    <Avatar :name="row.name" size="md" />
                    <span class="min-w-0">
                      <span class="block truncate text-[14.5px] font-semibold text-ink">{{ row.name }}</span>
                      <span class="mt-0.5 block text-[12px] tabular text-ink-faint">
                        {{ row.today.window_minutes === null ? t('workload.noShiftToday') : t('workload.travelledToday', { distance: formatDistance(row.today.distance_m) }) }}
                      </span>
                    </span>
                  </span>

                  <span class="grid flex-none grid-cols-3 gap-x-4 gap-y-2.5 sm:grid-cols-6 xl:w-[300px]">
                    <span class="min-w-0">
                      <span class="eyebrow block">{{ t('workload.open') }}</span>
                      <span class="block text-[15px] font-bold tabular text-ink">{{ formatNumber(row.summary.active_cases) }}</span>
                    </span>
                    <span class="min-w-0">
                      <span class="eyebrow block">{{ t('workload.pendingShort') }}</span>
                      <span class="block text-[15px] font-bold tabular text-ink">{{ formatNumber(row.summary.pending) }}</span>
                    </span>
                    <span class="min-w-0">
                      <span class="eyebrow block">{{ t('workload.scheduledShort') }}</span>
                      <span class="block text-[15px] font-bold tabular text-ink">{{ formatNumber(row.summary.scheduled) }}</span>
                    </span>
                    <span class="min-w-0">
                      <span class="eyebrow block">{{ t('workload.late') }}</span>
                      <span
                        class="block text-[15px] font-bold tabular"
                        :class="row.summary.overdue > 0 ? 'text-state-danger' : 'text-ink'"
                      >{{ formatNumber(row.summary.overdue) }}</span>
                    </span>
                    <span class="min-w-0">
                      <span class="eyebrow block">{{ t('workload.weekShort') }}</span>
                      <span class="block text-[15px] font-bold tabular text-ink">{{ formatNumber(row.summary.completed_week) }}</span>
                    </span>
                    <span class="min-w-0">
                      <span class="eyebrow block">{{ t('workload.monthShort') }}</span>
                      <span class="block text-[15px] font-bold tabular text-ink">{{ formatNumber(row.summary.completed_month) }}</span>
                    </span>
                  </span>

                  <span class="flex flex-none items-center gap-3 xl:w-[280px]">
                    <span class="relative grid h-11 w-11 flex-none place-items-center rounded-full" :style="ringStyle(row.today)">
                      <span class="grid h-8 w-8 place-items-center rounded-full bg-surface">
                        <span class="text-[11px] font-bold tabular text-ink">{{ formatNumber(utilizationPct(row.today)) }}%</span>
                      </span>
                    </span>
                    <span class="min-w-0 flex-1">
                      <span class="flex h-2 w-full overflow-hidden rounded-pill bg-surface-sunken">
                        <span class="bg-primary" :style="{ width: segments(row.today).inspection + '%' }" />
                        <span class="bg-state-warning" :style="{ width: segments(row.today).travel + '%' }" />
                        <span class="bg-state-neutral" :style="{ width: segments(row.today).idle + '%' }" />
                      </span>
                      <span class="mt-1.5 block text-[11.5px] tabular text-ink-faint">
                        {{ t('workload.inspectingTime', { duration: minutesLabel(row.today.inspection_minutes) }) }} ·
                        {{ t('workload.travellingTime', { duration: minutesLabel(row.today.travel_minutes) }) }}
                      </span>
                    </span>
                  </span>

                  <span class="flex flex-none items-center gap-2 xl:w-40 xl:justify-end">
                    <Badge :variant="statusOf(row).variant">{{ statusOf(row).label }}</Badge>
                    <Icon name="chevron-right" class="directional-icon hidden h-4 w-4 text-ink-faint xl:block" />
                  </span>
                </button>
              </li>
            </ul>
          </div>

          <div class="flex flex-none flex-wrap items-center gap-4 border-t border-hairline px-4 py-2.5 text-[11.5px] text-ink-soft sm:px-5">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-primary"></span>{{ t('workload.inspecting') }}</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-state-warning"></span>{{ t('workload.travelling') }}</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-state-neutral"></span>{{ t('workload.idle') }}</span>
          </div>
        </div>
      </Card>
    </div>

    <Drawer v-model="drawerOpen" :title="drawerEmployeeName">
      <div class="space-y-4">
        <div>
          <label for="workload-date" class="mb-1.5 block text-[12px] font-medium text-ink-soft">{{ t('workload.activityDate') }}</label>
          <input
            id="workload-date"
            v-model="drawerDate"
            type="date"
            :placeholder="t('workload.pickDate')"
            :max="todayLocalDate()"
            class="field w-52"
          />
        </div>

        <InlineAlert v-if="drawerError" class="!mb-0">{{ drawerError }}</InlineAlert>

        <div v-if="drawerLoading" class="space-y-2.5">
          <Skeleton class="h-20" rounded="md" />
          <Skeleton class="h-28" rounded="md" />
        </div>

        <template v-else-if="drawerDetail">
          <section>
            <h2 class="mb-2.5">{{ t('workload.caseLoad') }}</h2>
            <div class="grid grid-cols-2 gap-2.5">
              <StatCard tone="sunken" icon="briefcase" :label="t('workload.open')" :value="formatNumber(drawerDetail.summary.active_cases)" accent="primary" />
              <StatCard tone="sunken" icon="inbox" :label="t('workload.pending')" :value="formatNumber(drawerDetail.summary.pending)" accent="neutral" />
              <StatCard tone="sunken" icon="calendar" :label="t('workload.scheduled')" :value="formatNumber(drawerDetail.summary.scheduled)" accent="neutral" />
              <StatCard
                tone="sunken"
                icon="alert-triangle"
                :label="t('workload.overdue')"
                :value="formatNumber(drawerDetail.summary.overdue)"
                :accent="drawerDetail.summary.overdue > 0 ? 'danger' : 'neutral'"
              />
            </div>
          </section>

          <section class="border-t border-hairline pt-4">
            <header class="mb-3 flex items-center justify-between gap-2">
              <h2>{{ t('workload.timeOnTask') }}</h2>
              <span class="text-[12px] tabular text-ink-faint">{{ formatDate(drawerDate) }}</span>
            </header>
            <div class="flex items-center gap-3.5">
              <div class="relative grid h-16 w-16 flex-none place-items-center rounded-full" :style="ringStyle(drawerDetail.activity)">
                <div class="grid h-11 w-11 place-items-center rounded-full bg-surface">
                  <span class="text-[13px] font-bold tabular text-ink">{{ formatNumber(utilizationPct(drawerDetail.activity)) }}%</span>
                </div>
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex h-2.5 w-full overflow-hidden rounded-pill bg-surface-sunken">
                  <div class="bg-primary" :style="{ width: segments(drawerDetail.activity).inspection + '%' }" />
                  <div class="bg-state-warning" :style="{ width: segments(drawerDetail.activity).travel + '%' }" />
                  <div class="bg-state-neutral" :style="{ width: segments(drawerDetail.activity).idle + '%' }" />
                </div>
                <p class="mt-2 tabular text-[12px] text-ink-faint">
                  {{ t('workload.travelled', { distance: formatDistance(drawerDetail.activity.distance_m) }) }}
                </p>
              </div>
            </div>

            <dl class="mt-4 grid grid-cols-3 gap-2.5 border-t border-hairline pt-4 text-[13px]">
              <div>
                <dt class="eyebrow mb-1">{{ t('workload.inspecting') }}</dt>
                <dd class="tabular font-semibold text-ink">{{ minutesLabel(drawerDetail.activity.inspection_minutes) }}</dd>
              </div>
              <div>
                <dt class="eyebrow mb-1">{{ t('workload.travelling') }}</dt>
                <dd class="tabular font-semibold text-ink">{{ minutesLabel(drawerDetail.activity.travel_minutes) }}</dd>
              </div>
              <div>
                <dt class="eyebrow mb-1">{{ t('workload.idle') }}</dt>
                <dd class="tabular font-semibold text-ink">{{ minutesLabel(drawerDetail.activity.idle_minutes) }}</dd>
              </div>
            </dl>

            <p v-if="drawerDetail.activity.window_minutes === null" class="mt-3 text-[12px] text-ink-faint">
              {{ t('workload.noWindow') }}
            </p>
          </section>

          <Button variant="secondary" class="w-full justify-center" :to="`/employees/${drawerDetail.employee_id}/histories`">
            <Icon name="route" class="h-4 w-4" />
            {{ t('workload.openHistory') }}
          </Button>
        </template>
      </div>
    </Drawer>
  </AppShell>
</template>
