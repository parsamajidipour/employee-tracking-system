<script setup lang="ts">
import type { CaseStatus } from '~/composables/useCases'
import { CASE_STATUSES, caseStatusLabel, casePriorityLabel, casePriorityVariant, caseAssignmentDisplay } from '~/utils/caseStatus'

const { data: employeesData, load: loadEmployees } = useEmployees()
const employees = computed(() => employeesData.value ?? [])

const { data: cases, meta, loading, error, load } = useCasesList()

const statusFilter = ref<CaseStatus | ''>('')
const assigneeFilter = ref<number | ''>('')
const page = ref(1)

function currentFilters() {
  return {
    status: statusFilter.value || undefined,
    assigned_to: assigneeFilter.value || undefined,
    page: page.value,
  }
}

function refresh() {
  return load(currentFilters())
}

watch([statusFilter, assigneeFilter], () => {
  page.value = 1
  refresh()
})

function goToPage(next: number) {
  if (!meta.value || next < 1 || next > meta.value.last_page) return
  page.value = next
  refresh()
}

useCaseStream(() => { refresh() })

onMounted(() => {
  loadEmployees()
  refresh()
})
</script>

<template>
  <AppShell title="Cases" :subtitle="meta ? `${meta.total} total` : undefined">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" aria-label="Refresh cases" @click="refresh">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
      <Button size="sm" to="/cases/new">
        <Icon name="plus" class="h-3.5 w-3.5" />
        <span class="hidden sm:inline">New case</span>
      </Button>
    </template>

    <div class="surface-flat mb-3 flex flex-wrap items-end gap-3 p-3.5 sm:mb-4 sm:gap-3.5 sm:p-4">
      <div class="w-full min-[360px]:w-48">
        <Select v-model="statusFilter" label="Status">
          <option value="">All statuses</option>
          <option v-for="status in CASE_STATUSES" :key="status" :value="status">{{ caseStatusLabel(status) }}</option>
        </Select>
      </div>
      <div class="w-full min-[360px]:w-56">
        <Select v-model="assigneeFilter" label="Assignee">
          <option value="">All employees</option>
          <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
        </Select>
      </div>
    </div>

    <Table
      :headers="['Reference', 'Title', 'Assignee', 'Status', 'Priority', 'Created', '']"
      :loading="loading"
      :error="error"
      :is-empty="cases.length === 0"
      empty-message="No cases match these filters."
    >
      <template #cards>
        <NuxtLink v-for="item in cases" :key="item.id" :to="`/cases/${item.id}`" class="surface-flat block space-y-3 p-3.5 sm:p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate text-[14px] font-medium text-ink">{{ item.title }}</p>
              <p class="truncate text-[12px] text-ink-faint">{{ item.reference_no }}</p>
            </div>
            <Badge :variant="caseAssignmentDisplay(item).variant">{{ caseAssignmentDisplay(item).label }}</Badge>
          </div>
          <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-[13px]">
            <div>
              <dt class="eyebrow mb-1">Assignee</dt>
              <dd class="text-ink">{{ item.assignee_name ?? 'Unassigned' }}</dd>
            </div>
            <div>
              <dt class="eyebrow mb-1">Priority</dt>
              <dd><Badge :variant="casePriorityVariant(item.priority)">{{ casePriorityLabel(item.priority) }}</Badge></dd>
            </div>
          </dl>
        </NuxtLink>
      </template>

      <tr v-for="item in cases" :key="item.id" class="row-h cursor-pointer text-ink hover:bg-surface-sunken/60" @click="navigateTo(`/cases/${item.id}`)">
        <td class="px-5 text-[14px] font-medium tabular">{{ item.reference_no }}</td>
        <td class="px-5 text-[14px]">{{ item.title }}</td>
        <td class="px-5 text-[14px] text-ink-soft">{{ item.assignee_name ?? 'Unassigned' }}</td>
        <td class="px-5"><Badge :variant="caseAssignmentDisplay(item).variant">{{ caseAssignmentDisplay(item).label }}</Badge></td>
        <td class="px-5"><Badge :variant="casePriorityVariant(item.priority)">{{ casePriorityLabel(item.priority) }}</Badge></td>
        <td class="px-5 text-[13px] tabular text-ink-faint">{{ new Date(item.created_at).toLocaleDateString() }}</td>
        <td class="px-5 text-right">
          <NuxtLink :to="`/cases/${item.id}`" class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken" @click.stop>
            View
          </NuxtLink>
        </td>
      </tr>
    </Table>

    <div v-if="meta && meta.last_page > 1" class="mt-4 flex flex-wrap items-center justify-between gap-3">
      <p class="text-[12.5px] text-ink-faint">Page {{ meta.current_page }} of {{ meta.last_page }} — {{ meta.total }} cases</p>
      <div class="flex items-center gap-2">
        <Button variant="secondary" size="sm" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">Prev</Button>
        <Button variant="secondary" size="sm" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">Next</Button>
      </div>
    </div>
  </AppShell>
</template>
