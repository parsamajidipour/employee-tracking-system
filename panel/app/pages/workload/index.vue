<script setup lang="ts">
import type { WorkloadDetail, WorkloadActivity } from '~/composables/useWorkload'

function todayLocalDate(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

const { data: rows, loading, error, load } = useWorkloadList()

useCaseAssignmentAlerts(load)

const totals = computed(() =>
  rows.value.reduce(
    (acc, row) => {
      acc.active += row.summary.active_cases
      acc.overdue += row.summary.overdue
      return acc
    },
    { active: 0, overdue: 0 },
  ),
)

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

function initials(name: string): string {
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('')
}

async function loadDrawerDetail() {
  if (drawerEmployeeId.value === null) return
  drawerLoading.value = true
  try {
    drawerDetail.value = await fetchWorkloadDetail(drawerEmployeeId.value, drawerDate.value)
    drawerError.value = null
  } catch {
    drawerError.value = 'Could not load activity for this day.'
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
  <AppShell title="Workload" :subtitle="rows.length ? `${rows.length} employees tracked` : undefined">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" @click="load">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
    </template>

    <InlineAlert v-if="error">{{ error }}</InlineAlert>

    <div v-if="loading && rows.length === 0" class="space-y-3">
      <Skeleton class="h-20" rounded="md" />
      <Skeleton class="h-32" rounded="md" />
      <Skeleton class="h-32" rounded="md" />
    </div>

    <EmptyState v-else-if="rows.length === 0" icon="briefcase" message="No active employees to show." />

    <template v-else>
      <div class="mb-5 grid grid-cols-2 gap-2.5 sm:grid-cols-3">
        <StatCard icon="users" label="Employees tracked" :value="String(rows.length)" accent="neutral" />
        <StatCard icon="briefcase" label="Active cases org-wide" :value="String(totals.active)" accent="primary" />
        <StatCard
          icon="alert-triangle"
          label="Overdue org-wide"
          :value="String(totals.overdue)"
          :accent="totals.overdue > 0 ? 'danger' : 'neutral'"
        />
      </div>

      <div class="space-y-4">
        <div v-for="row in rows" :key="row.employee_id" class="surface-flat p-5">
          <div class="mb-4 flex items-center justify-between gap-3">
            <button
              type="button"
              class="flex min-w-0 items-center gap-3 text-left"
              @click="openDrawer(row.employee_id, row.name)"
            >
              <span class="grid h-10 w-10 flex-none place-items-center rounded-full bg-primary-soft text-[13px] font-bold text-primary-strong">
                {{ initials(row.name) }}
              </span>
              <span class="min-w-0">
                <span class="block truncate text-[15px] font-semibold text-ink hover:text-primary-strong">{{ row.name }}</span>
                <span class="block text-[12px] text-ink-faint">View activity history →</span>
              </span>
            </button>
            <Badge v-if="row.summary.overdue > 0" variant="warning">{{ row.summary.overdue }} overdue</Badge>
            <Badge v-else variant="success">On track</Badge>
          </div>

          <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6">
            <StatCard icon="briefcase" label="Active" :value="String(row.summary.active_cases)" accent="primary" />
            <StatCard icon="inbox" label="Pending" :value="String(row.summary.pending)" accent="neutral" />
            <StatCard icon="calendar" label="Scheduled" :value="String(row.summary.scheduled)" accent="neutral" />
            <StatCard icon="check-circle" label="Done today" :value="String(row.summary.completed_today)" accent="success" />
            <StatCard icon="check-circle" label="Done this week" :value="String(row.summary.completed_week)" accent="success" />
            <StatCard icon="check-circle" label="Done this month" :value="String(row.summary.completed_month)" accent="success" />
          </div>

          <div class="mt-4 flex flex-col gap-4 border-t border-hairline pt-4 sm:flex-row sm:items-center">
            <div class="flex flex-none items-center gap-3">
              <div class="relative grid h-16 w-16 flex-none place-items-center rounded-full" :style="ringStyle(row.today)">
                <div class="grid h-11 w-11 place-items-center rounded-full bg-surface">
                  <span class="text-[13px] font-bold tabular text-ink">{{ utilizationPct(row.today) }}%</span>
                </div>
              </div>
              <div class="text-[12px] leading-tight">
                <p class="font-semibold text-ink">Utilization today</p>
                <p v-if="row.today.window_minutes === null" class="text-ink-faint">No shift window today</p>
                <p v-else class="tabular text-ink-faint">{{ formatDistance(row.today.distance_m) }} travelled</p>
              </div>
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex h-2.5 w-full overflow-hidden rounded-pill bg-surface-sunken">
                <div class="bg-primary" :style="{ width: segments(row.today).inspection + '%' }" />
                <div class="bg-state-warning" :style="{ width: segments(row.today).travel + '%' }" />
                <div class="bg-state-neutral" :style="{ width: segments(row.today).idle + '%' }" />
              </div>
              <div class="mt-2 flex flex-wrap gap-3.5 text-[12px] text-ink-soft">
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-primary"></span>Inspecting {{ minutesLabel(row.today.inspection_minutes) }}</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-state-warning"></span>Travelling {{ minutesLabel(row.today.travel_minutes) }}</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-state-neutral"></span>Idle {{ minutesLabel(row.today.idle_minutes) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <Drawer v-model="drawerOpen" :title="drawerEmployeeName">
      <div class="mb-4">
        <label class="mb-1.5 block text-[12.5px] font-medium text-ink-soft">Date</label>
        <input v-model="drawerDate" type="date" :max="todayLocalDate()" class="field w-48" />
      </div>

      <InlineAlert v-if="drawerError">{{ drawerError }}</InlineAlert>

      <div v-if="drawerLoading" class="space-y-2.5">
        <Skeleton class="h-12" rounded="md" />
        <Skeleton class="h-12" rounded="md" />
      </div>

      <template v-else-if="drawerDetail">
        <div class="mb-4 grid grid-cols-2 gap-2.5">
          <StatCard icon="briefcase" label="Active" :value="String(drawerDetail.summary.active_cases)" accent="primary" />
          <StatCard icon="inbox" label="Pending" :value="String(drawerDetail.summary.pending)" accent="neutral" />
          <StatCard icon="check-circle" label="Completed" :value="String(drawerDetail.summary.completed_today)" accent="success" />
          <StatCard icon="calendar" label="Scheduled" :value="String(drawerDetail.summary.scheduled)" accent="neutral" />
        </div>

        <div class="flex items-center gap-3 border-t border-hairline pt-4">
          <div class="relative grid h-14 w-14 flex-none place-items-center rounded-full" :style="ringStyle(drawerDetail.activity)">
            <div class="grid h-9 w-9 place-items-center rounded-full bg-surface">
              <span class="text-[11px] font-bold tabular text-ink">{{ utilizationPct(drawerDetail.activity) }}%</span>
            </div>
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex h-2.5 w-full overflow-hidden rounded-pill bg-surface-sunken">
              <div class="bg-primary" :style="{ width: segments(drawerDetail.activity).inspection + '%' }" />
              <div class="bg-state-warning" :style="{ width: segments(drawerDetail.activity).travel + '%' }" />
              <div class="bg-state-neutral" :style="{ width: segments(drawerDetail.activity).idle + '%' }" />
            </div>
            <p class="mt-1.5 tabular text-[12px] text-ink-faint">{{ formatDistance(drawerDetail.activity.distance_m) }} travelled</p>
          </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-3.5 text-[12px] text-ink-soft">
          <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-primary"></span>Inspecting {{ minutesLabel(drawerDetail.activity.inspection_minutes) }}</span>
          <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-state-warning"></span>Travelling {{ minutesLabel(drawerDetail.activity.travel_minutes) }}</span>
          <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-state-neutral"></span>Idle {{ minutesLabel(drawerDetail.activity.idle_minutes) }}</span>
        </div>
      </template>
    </Drawer>
  </AppShell>
</template>
