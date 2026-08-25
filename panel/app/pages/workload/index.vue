<script setup lang="ts">
import type { WorkloadDetail, WorkloadActivity, WorkloadRow } from '~/composables/useWorkload'

definePageMeta({ middleware: 'employees-workload' })

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
  if (h === 0) return `${m}m`
  return `${h}h ${m}m`
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
  if (row.summary.overdue > 0) return { label: `${row.summary.overdue} overdue`, variant: 'danger' }
  if (row.summary.active_cases === 0) return { label: 'No open work', variant: 'neutral' }
  return { label: 'On track', variant: 'success' }
}

async function loadDrawerDetail() {
  if (drawerEmployeeId.value === null) return
  drawerLoading.value = true
  try {
    drawerDetail.value = await fetchWorkloadDetail(drawerEmployeeId.value, drawerDate.value)
    drawerError.value = null
  } catch (err) {
    drawerError.value = apiErrorMessage(err, 'Could not load activity for this day.')
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
  <AppShell title="Workload" subtitle="Open work and time-on-task, per surveyor" full-bleed>
    <template #actions>
      <span
        class="hidden items-center gap-1.5 rounded-pill bg-surface-sunken px-2.5 py-1.5 text-[11.5px] font-semibold text-ink-soft sm:inline-flex"
        :title="connected ? 'Updating live over websocket' : 'Live updates unavailable — use Refresh'"
      >
        <span class="h-1.5 w-1.5 rounded-full" :class="connected ? 'bg-state-success' : 'bg-state-neutral'"></span>
        {{ connected ? 'Live' : 'Offline' }}
      </span>
      <Button variant="secondary" size="sm" :disabled="loading" aria-label="Refresh workload" @click="load">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
    </template>

    <div class="flex h-full min-h-0 flex-col gap-4 overflow-y-auto p-3 sm:p-5 lg:overflow-hidden">
      <InlineAlert v-if="error" class="!mb-0 flex-none">{{ error }}</InlineAlert>

      <div class="grid flex-none grid-cols-2 gap-2.5 lg:grid-cols-4">
        <StatCard icon="users" label="Surveyors tracked" :value="String(rows.length)" accent="neutral" />
        <StatCard icon="briefcase" label="Open cases org-wide" :value="String(totals.active)" accent="primary" />
        <StatCard
          icon="alert-triangle"
          label="Overdue org-wide"
          :value="String(totals.overdue)"
          :accent="totals.overdue > 0 ? 'danger' : 'neutral'"
        />
        <StatCard icon="check-circle" label="Completed today" :value="String(totals.completedToday)" accent="success" />
      </div>

      <Card
        class="flex-none lg:min-h-0 lg:flex-1"
        icon="chart-bar"
        title="By surveyor"
        :subtitle="`${visibleRows.length} shown of ${rows.length}`"
        flush
      >
        <div class="flex min-h-0 flex-col lg:h-full">
          <div class="flex flex-none flex-wrap items-end gap-3 border-b border-hairline bg-surface-sunken/60 px-4 py-3 sm:px-5">
            <div class="min-w-56 flex-1">
              <TextInput v-model="search" label="Search" icon="search" placeholder="Search a surveyor by name" />
            </div>
            <div class="w-52">
              <Select v-model="sortKey" label="Sort by">
                <option value="overdue">Most overdue first</option>
                <option value="active">Most open cases first</option>
                <option value="completed">Most completed this week</option>
                <option value="name">Name (A–Z)</option>
              </Select>
            </div>
          </div>

          <div class="min-h-0 lg:flex-1 lg:overflow-y-auto">
            <div v-if="loading && rows.length === 0" class="space-y-2.5 p-4 sm:p-5">
              <Skeleton v-for="i in 5" :key="i" class="h-16" rounded="md" />
            </div>

            <EmptyState
              v-else-if="visibleRows.length === 0"
              icon="briefcase"
              :message="rows.length === 0 ? 'No active employees to show.' : 'No surveyor matches this search.'"
              class="py-12"
            />

            <ul v-else class="divide-y divide-hairline">
              <li v-for="row in visibleRows" :key="row.employee_id">
                <button
                  type="button"
                  class="flex w-full flex-col gap-3.5 px-4 py-3.5 text-left transition-colors duration-fast ease-soft hover:bg-surface-sunken/60 sm:px-5 xl:flex-row xl:items-center xl:gap-5"
                  @click="openDrawer(row.employee_id, row.name)"
                >
                  <span class="flex min-w-0 flex-1 items-center gap-3">
                    <Avatar :name="row.name" size="md" />
                    <span class="min-w-0">
                      <span class="block truncate text-[14.5px] font-semibold text-ink">{{ row.name }}</span>
                      <span class="mt-0.5 block text-[12px] tabular text-ink-faint">
                        {{ row.today.window_minutes === null ? 'No shift window today' : `${formatDistance(row.today.distance_m)} travelled today` }}
                      </span>
                    </span>
                  </span>

                  <span class="grid flex-none grid-cols-3 gap-x-4 gap-y-2.5 sm:grid-cols-6 xl:w-[300px]">
                    <span class="min-w-0">
                      <span class="eyebrow block">Open</span>
                      <span class="block text-[15px] font-bold tabular text-ink">{{ row.summary.active_cases }}</span>
                    </span>
                    <span class="min-w-0">
                      <span class="eyebrow block">Pend</span>
                      <span class="block text-[15px] font-bold tabular text-ink">{{ row.summary.pending }}</span>
                    </span>
                    <span class="min-w-0">
                      <span class="eyebrow block">Sched</span>
                      <span class="block text-[15px] font-bold tabular text-ink">{{ row.summary.scheduled }}</span>
                    </span>
                    <span class="min-w-0">
                      <span class="eyebrow block">Late</span>
                      <span
                        class="block text-[15px] font-bold tabular"
                        :class="row.summary.overdue > 0 ? 'text-state-danger' : 'text-ink'"
                      >{{ row.summary.overdue }}</span>
                    </span>
                    <span class="min-w-0">
                      <span class="eyebrow block">Wk</span>
                      <span class="block text-[15px] font-bold tabular text-ink">{{ row.summary.completed_week }}</span>
                    </span>
                    <span class="min-w-0">
                      <span class="eyebrow block">Mo</span>
                      <span class="block text-[15px] font-bold tabular text-ink">{{ row.summary.completed_month }}</span>
                    </span>
                  </span>

                  <span class="flex flex-none items-center gap-3 xl:w-[280px]">
                    <span class="relative grid h-11 w-11 flex-none place-items-center rounded-full" :style="ringStyle(row.today)">
                      <span class="grid h-8 w-8 place-items-center rounded-full bg-surface">
                        <span class="text-[11px] font-bold tabular text-ink">{{ utilizationPct(row.today) }}%</span>
                      </span>
                    </span>
                    <span class="min-w-0 flex-1">
                      <span class="flex h-2 w-full overflow-hidden rounded-pill bg-surface-sunken">
                        <span class="bg-primary" :style="{ width: segments(row.today).inspection + '%' }" />
                        <span class="bg-state-warning" :style="{ width: segments(row.today).travel + '%' }" />
                        <span class="bg-state-neutral" :style="{ width: segments(row.today).idle + '%' }" />
                      </span>
                      <span class="mt-1.5 block text-[11.5px] tabular text-ink-faint">
                        {{ minutesLabel(row.today.inspection_minutes) }} inspecting ·
                        {{ minutesLabel(row.today.travel_minutes) }} travelling
                      </span>
                    </span>
                  </span>

                  <span class="flex flex-none items-center gap-2 xl:w-40 xl:justify-end">
                    <Badge :variant="statusOf(row).variant">{{ statusOf(row).label }}</Badge>
                    <Icon name="chevron-right" class="hidden h-4 w-4 text-ink-faint xl:block" />
                  </span>
                </button>
              </li>
            </ul>
          </div>

          <div class="flex flex-none flex-wrap items-center gap-4 border-t border-hairline px-4 py-2.5 text-[11.5px] text-ink-soft sm:px-5">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-primary"></span>Inspecting</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-state-warning"></span>Travelling</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-state-neutral"></span>Idle</span>
          </div>
        </div>
      </Card>
    </div>

    <Drawer v-model="drawerOpen" :title="drawerEmployeeName">
      <div class="space-y-4">
        <div>
          <label for="workload-date" class="mb-1.5 block text-[12px] font-medium text-ink-soft">Activity date</label>
          <input
            id="workload-date"
            v-model="drawerDate"
            type="date"
            placeholder="Pick a date"
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
            <h2 class="mb-2.5">Case load</h2>
            <div class="grid grid-cols-2 gap-2.5">
              <StatCard tone="sunken" icon="briefcase" label="Open" :value="String(drawerDetail.summary.active_cases)" accent="primary" />
              <StatCard tone="sunken" icon="inbox" label="Pending" :value="String(drawerDetail.summary.pending)" accent="neutral" />
              <StatCard tone="sunken" icon="calendar" label="Scheduled" :value="String(drawerDetail.summary.scheduled)" accent="neutral" />
              <StatCard
                tone="sunken"
                icon="alert-triangle"
                label="Overdue"
                :value="String(drawerDetail.summary.overdue)"
                :accent="drawerDetail.summary.overdue > 0 ? 'danger' : 'neutral'"
              />
            </div>
          </section>

          <section class="border-t border-hairline pt-4">
            <header class="mb-3 flex items-center justify-between gap-2">
              <h2>Time on task</h2>
              <span class="text-[12px] tabular text-ink-faint">{{ new Date(drawerDate).toLocaleDateString() }}</span>
            </header>
            <div class="flex items-center gap-3.5">
              <div class="relative grid h-16 w-16 flex-none place-items-center rounded-full" :style="ringStyle(drawerDetail.activity)">
                <div class="grid h-11 w-11 place-items-center rounded-full bg-surface">
                  <span class="text-[13px] font-bold tabular text-ink">{{ utilizationPct(drawerDetail.activity) }}%</span>
                </div>
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex h-2.5 w-full overflow-hidden rounded-pill bg-surface-sunken">
                  <div class="bg-primary" :style="{ width: segments(drawerDetail.activity).inspection + '%' }" />
                  <div class="bg-state-warning" :style="{ width: segments(drawerDetail.activity).travel + '%' }" />
                  <div class="bg-state-neutral" :style="{ width: segments(drawerDetail.activity).idle + '%' }" />
                </div>
                <p class="mt-2 tabular text-[12px] text-ink-faint">
                  {{ formatDistance(drawerDetail.activity.distance_m) }} travelled
                </p>
              </div>
            </div>

            <dl class="mt-4 grid grid-cols-3 gap-2.5 border-t border-hairline pt-4 text-[13px]">
              <div>
                <dt class="eyebrow mb-1">Inspecting</dt>
                <dd class="tabular font-semibold text-ink">{{ minutesLabel(drawerDetail.activity.inspection_minutes) }}</dd>
              </div>
              <div>
                <dt class="eyebrow mb-1">Travelling</dt>
                <dd class="tabular font-semibold text-ink">{{ minutesLabel(drawerDetail.activity.travel_minutes) }}</dd>
              </div>
              <div>
                <dt class="eyebrow mb-1">Idle</dt>
                <dd class="tabular font-semibold text-ink">{{ minutesLabel(drawerDetail.activity.idle_minutes) }}</dd>
              </div>
            </dl>

            <p v-if="drawerDetail.activity.window_minutes === null" class="mt-3 text-[12px] text-ink-faint">
              No shift window resolved for this day — nothing was tracked.
            </p>
          </section>

          <Button variant="secondary" class="w-full justify-center" :to="`/employees/${drawerDetail.employee_id}/histories`">
            <Icon name="route" class="h-4 w-4" />
            Open route history
          </Button>
        </template>
      </div>
    </Drawer>
  </AppShell>
</template>
