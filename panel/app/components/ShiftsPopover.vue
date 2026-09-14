<script setup lang="ts">
import type { EmployeeShiftSummary } from '~/composables/useEmployees'

const props = defineProps<{ shifts: EmployeeShiftSummary[] }>()
const { t } = useI18n()
const { number } = useLocalizedFormat()
</script>

<template>
  <span class="inline-flex items-center gap-1.5">
    <span class="tabular text-[13.5px] text-ink">{{ t('shifts.shiftCount', { count: number(shifts.length) }) }}</span>
    <Popover :width="272" align="end" :label="t('shifts.showDetails')">
      <template #trigger="{ open, toggle }">
        <button
          type="button"
          class="grid h-6 w-6 shrink-0 place-items-center rounded-sm text-ink-faint transition-colors hover:bg-surface-sunken hover:text-primary-strong"
          :class="open ? 'bg-surface-sunken text-primary-strong' : ''"
          :aria-expanded="open"
          :aria-label="t('shifts.showDetails')"
          @click.stop="toggle"
        >
          <Icon name="calendar" class="h-3.5 w-3.5" />
        </button>
      </template>
      <div class="p-1.5">
        <p class="eyebrow mb-2">{{ t('shifts.assignedCount', { count: number(shifts.length) }) }}</p>
        <ul class="space-y-1.5">
          <li v-for="shift in shifts" :key="shift.id" class="flex items-center justify-between gap-3 text-[13px]">
            <span class="min-w-0 truncate font-medium text-ink">{{ shift.name }}</span>
            <span class="shrink-0 tabular text-ink-soft">{{ shift.start_time.slice(0, 5) }}–{{ shift.end_time.slice(0, 5) }}</span>
          </li>
        </ul>
      </div>
    </Popover>
  </span>
</template>
