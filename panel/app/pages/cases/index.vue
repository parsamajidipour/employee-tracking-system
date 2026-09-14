<script setup lang="ts">
import type { CaseStatus } from '~/composables/useCases'
import { CASE_STATUSES, casePriorityVariant, caseAssignmentDisplay } from '~/utils/caseStatus'

const { t } = useI18n()
const { date, number } = useLocalizedFormat()

const { data: employeesData, load: loadEmployees } = useEmployees()
const employees = computed(() => employeesData.value ?? [])

const { data: cases, meta, loading, error, load } = useCasesList()

const statusFilter = ref<CaseStatus | ''>('')
const assigneeFilter = ref<number | ''>('')
const createdDateFilter = ref('')
const page = ref(1)
const advancedFilterCount = computed(() => Number(assigneeFilter.value !== '') + Number(createdDateFilter.value !== ''))

function currentFilters() {
  return {
    status: statusFilter.value || undefined,
    assigned_to: assigneeFilter.value || undefined,
    created_date: createdDateFilter.value || undefined,
    page: page.value,
  }
}

function refresh() {
  return load(currentFilters())
}

watch([statusFilter, assigneeFilter, createdDateFilter], () => {
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
  <AppShell :title="t('cases.list.title')" :subtitle="meta ? t('cases.list.total', { count: number(meta.total) }) : undefined">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" :aria-label="t('cases.list.refresh')" @click="refresh">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">{{ t('common.refresh') }}</span>
      </Button>
    </template>

    <div class="mb-3 flex justify-end sm:mb-4">
      <Button class="w-full sm:w-auto" to="/cases/new">
        <Icon name="plus" class="h-4 w-4" />
        <span>{{ t('cases.new.title') }}</span>
      </Button>
    </div>

    <div class="surface-flat mb-3 grid grid-cols-2 items-end gap-2.5 p-3 sm:mb-4 sm:flex sm:flex-wrap sm:gap-3.5 sm:p-4">
      <div class="min-w-0 sm:w-48">
        <Select v-model="statusFilter" :label="t('common.status')">
          <option value="">{{ t('cases.list.allStatuses') }}</option>
          <option v-for="status in CASE_STATUSES" :key="status" :value="status">{{ t(`case.statuses.${status}`) }}</option>
        </Select>
      </div>
      <ResponsiveFilterGroup :title="t('common.moreFilters')" :active-count="advancedFilterCount">
        <div class="w-full sm:w-56">
          <Select v-model="assigneeFilter" :label="t('cases.fields.assignee')">
            <option value="">{{ t('cases.list.allEmployees') }}</option>
            <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
          </Select>
        </div>
        <DateInput v-model="createdDateFilter" class="w-full sm:w-48" :label="t('common.date')" />
        <template #footer>
          <Button
            v-if="advancedFilterCount > 0"
            variant="ghost"
            size="sm"
            type="button"
            class="w-full justify-center sm:w-auto"
            @click="assigneeFilter = ''; createdDateFilter = ''"
          >
            <Icon name="close" class="h-3.5 w-3.5" />
            {{ t('common.clearFilters') }}
          </Button>
        </template>
      </ResponsiveFilterGroup>
    </div>

    <Table
      :headers="[t('cases.fields.reportNumber'), t('cases.fields.customerName'), t('cases.fields.assignee'), t('common.status'), t('cases.fields.priority'), t('cases.fields.created'), '']"
      :loading="loading"
      :error="error"
      :is-empty="cases.length === 0"
      :empty-message="t('cases.list.empty')"
    >
      <template #cards>
        <NuxtLink v-for="item in cases" :key="item.id" :to="`/cases/${item.id}`" class="surface-flat block p-3.5 sm:p-4">
          <div class="flex min-w-0 items-start justify-between gap-2.5">
            <div class="min-w-0 flex-1">
              <p class="truncate text-[14px] font-medium text-ink">{{ item.title }}</p>
              <p class="truncate text-[12px] text-ink-faint">{{ item.reference_no }}</p>
            </div>
            <div class="flex max-w-[58%] flex-none flex-wrap items-center justify-end gap-1.5">
              <Badge :variant="caseAssignmentDisplay(item).variant">{{ t(`case.assignmentStatuses.${caseAssignmentDisplay(item).status}`) }}</Badge>
              <Badge :variant="casePriorityVariant(item.priority)">{{ t(`case.priorities.${item.priority}`) }}</Badge>
            </div>
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-3 border-t border-hairline pt-3 text-[13px]">
            <div class="min-w-0">
              <dt class="eyebrow mb-1">{{ t('cases.fields.assignee') }}</dt>
              <dd class="truncate text-ink">{{ item.assignee_name ?? t('case.assignmentStatuses.unassigned') }}</dd>
            </div>
            <div class="min-w-0 text-end">
              <dt class="eyebrow mb-1">{{ t('cases.fields.created') }}</dt>
              <dd class="tabular text-ink-soft">{{ date(item.created_at) }}</dd>
            </div>
          </dl>
        </NuxtLink>
      </template>

      <tr v-for="item in cases" :key="item.id" class="row-h cursor-pointer text-ink hover:bg-surface-sunken/60" @click="navigateTo(`/cases/${item.id}`)">
        <td class="px-5 text-[14px] font-medium tabular">{{ item.reference_no }}</td>
        <td class="px-5 text-[14px]">{{ item.title }}</td>
        <td class="px-5 text-[14px] text-ink-soft">{{ item.assignee_name ?? t('case.assignmentStatuses.unassigned') }}</td>
        <td class="px-5"><Badge :variant="caseAssignmentDisplay(item).variant">{{ t(`case.assignmentStatuses.${caseAssignmentDisplay(item).status}`) }}</Badge></td>
        <td class="px-5"><Badge :variant="casePriorityVariant(item.priority)">{{ t(`case.priorities.${item.priority}`) }}</Badge></td>
        <td class="px-5 text-[13px] tabular text-ink-faint">{{ date(item.created_at) }}</td>
        <td class="px-5 text-end">
          <NuxtLink :to="`/cases/${item.id}`" class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken" @click.stop>
            {{ t('cases.list.view') }}
          </NuxtLink>
        </td>
      </tr>
    </Table>

    <div v-if="meta && meta.last_page > 1" class="mt-4 flex flex-wrap items-center justify-between gap-3">
      <p class="text-[12.5px] text-ink-faint">{{ t('cases.list.page', { current: number(meta.current_page), last: number(meta.last_page), total: number(meta.total) }) }}</p>
      <div class="flex items-center gap-2">
        <Button variant="secondary" size="sm" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">{{ t('common.previous') }}</Button>
        <Button variant="secondary" size="sm" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">{{ t('common.next') }}</Button>
      </div>
    </div>
  </AppShell>
</template>
