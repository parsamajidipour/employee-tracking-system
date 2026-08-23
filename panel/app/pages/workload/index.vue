<script setup lang="ts">
import type { WorkloadDetail } from '~/composables/useWorkload'

function todayLocalDate(): string {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

const { data: rows, loading, error, load } = useWorkloadList()

useCaseAssignmentAlerts(load)

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

function barSegments(activity: { inspection_minutes: number; travel_minutes: number; idle_minutes: number }) {
  const total = activity.inspection_minutes + activity.travel_minutes + activity.idle_minutes
  if (total <= 0) return { inspection: 0, travel: 0, idle: 100 }
  return {
    inspection: (activity.inspection_minutes / total) * 100,
    travel: (activity.travel_minutes / total) * 100,
    idle: (activity.idle_minutes / total) * 100,
  }
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
  <AppShell title="Workload" :subtitle="rows.length ? `${rows.length} employees` : undefined">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" @click="load">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
    </template>

    <InlineAlert v-if="error">{{ error }}</InlineAlert>

    <div v-if="loading && rows.length === 0" class="space-y-3">
      <Skeleton class="h-32" rounded="md" />
      <Skeleton class="h-32" rounded="md" />
    </div>

    <EmptyState v-else-if="rows.length === 0" message="No active employees to show." />

    <div v-else class="space-y-4">
      <div v-for="row in rows" :key="row.employee_id" class="surface-flat p-5">
        <div class="mb-3.5 flex items-center justify-between gap-3">
          <button type="button" class="text-[15px] font-semibold text-ink hover:text-primary-strong" @click="openDrawer(row.employee_id, row.name)">
            {{ row.name }}
          </button>
          <Badge v-if="row.summary.overdue > 0" variant="warning">{{ row.summary.overdue }} overdue</Badge>
        </div>

        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6">
          <StatCard icon="briefcase" label="Active" :value="String(row.summary.active_cases)" />
          <StatCard icon="inbox" label="Pending" :value="String(row.summary.pending)" />
          <StatCard icon="calendar" label="Scheduled" :value="String(row.summary.scheduled)" />
          <StatCard icon="check-circle" label="Done today" :value="String(row.summary.completed_today)" />
          <StatCard icon="check-circle" label="Done this week" :value="String(row.summary.completed_week)" />
          <StatCard icon="check-circle" label="Done this month" :value="String(row.summary.completed_month)" />
        </div>

        <div class="mt-4 border-t border-hairline pt-3.5">
          <div class="mb-1.5 flex items-center justify-between text-[12px] text-ink-faint">
            <span>Today's time split</span>
            <span v-if="row.today.window_minutes === null">No shift window today</span>
            <span v-else class="tabular">{{ formatDistance(row.today.distance_m) }} travelled</span>
          </div>
          <div class="flex h-2.5 w-full overflow-hidden rounded-pill bg-surface-sunken">
            <div class="bg-primary" :style="{ width: barSegments(row.today).inspection + '%' }" />
            <div class="bg-state-warning" :style="{ width: barSegments(row.today).travel + '%' }" />
            <div class="bg-state-neutral" :style="{ width: barSegments(row.today).idle + '%' }" />
          </div>
          <div class="mt-2 flex flex-wrap gap-3.5 text-[12px] text-ink-soft">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-primary"></span>Inspecting {{ minutesLabel(row.today.inspection_minutes) }}</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-state-warning"></span>Travelling {{ minutesLabel(row.today.travel_minutes) }}</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-state-neutral"></span>Idle {{ minutesLabel(row.today.idle_minutes) }}</span>
          </div>
        </div>
      </div>
    </div>

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
          <StatCard icon="briefcase" label="Active" :value="String(drawerDetail.summary.active_cases)" />
          <StatCard icon="inbox" label="Pending" :value="String(drawerDetail.summary.pending)" />
          <StatCard icon="check-circle" label="Completed" :value="String(drawerDetail.summary.completed_today)" />
          <StatCard icon="calendar" label="Scheduled" :value="String(drawerDetail.summary.scheduled)" />
        </div>

        <div class="border-t border-hairline pt-3.5">
          <div class="mb-1.5 flex items-center justify-between text-[12px] text-ink-faint">
            <span>Time split</span>
            <span class="tabular">{{ formatDistance(drawerDetail.activity.distance_m) }} travelled</span>
          </div>
          <div class="flex h-2.5 w-full overflow-hidden rounded-pill bg-surface-sunken">
            <div class="bg-primary" :style="{ width: barSegments(drawerDetail.activity).inspection + '%' }" />
            <div class="bg-state-warning" :style="{ width: barSegments(drawerDetail.activity).travel + '%' }" />
            <div class="bg-state-neutral" :style="{ width: barSegments(drawerDetail.activity).idle + '%' }" />
          </div>
          <div class="mt-2 flex flex-wrap gap-3.5 text-[12px] text-ink-soft">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-primary"></span>Inspecting {{ minutesLabel(drawerDetail.activity.inspection_minutes) }}</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-state-warning"></span>Travelling {{ minutesLabel(drawerDetail.activity.travel_minutes) }}</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-state-neutral"></span>Idle {{ minutesLabel(drawerDetail.activity.idle_minutes) }}</span>
          </div>
        </div>
      </template>
    </Drawer>
  </AppShell>
</template>
