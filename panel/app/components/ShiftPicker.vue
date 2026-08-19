<script setup lang="ts">
interface PickableShift {
  id: number
  name: string
  start_time: string
  end_time: string
  days_of_week: number[]
}

defineProps<{ shifts: PickableShift[]; loading?: boolean }>()
const selected = defineModel<number[]>({ default: () => [] })

const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

function formatTime(value: string) {
  return value.slice(0, 5)
}

function toggle(id: number) {
  selected.value = selected.value.includes(id)
    ? selected.value.filter((selectedId) => selectedId !== id)
    : [...selected.value, id]
}
</script>

<template>
  <div>
    <div v-if="loading" class="grid gap-2 sm:grid-cols-2">
      <Skeleton v-for="i in 4" :key="i" rounded="md" class="h-16" />
    </div>
    <div v-else-if="shifts.length" class="grid gap-2 sm:grid-cols-2">
      <button
        v-for="shift in shifts"
        :key="shift.id"
        type="button"
        class="flex items-center gap-3 rounded-md border p-3 text-left transition-colors duration-fast ease-soft"
        :class="selected.includes(shift.id) ? 'border-primary bg-primary-soft' : 'border-hairline bg-surface hover:border-primary/60'"
        @click="toggle(shift.id)"
      >
        <span
          class="grid h-5 w-5 flex-none place-items-center rounded-sm border transition-colors duration-fast"
          :class="selected.includes(shift.id) ? 'border-primary bg-primary text-white' : 'border-hairline text-transparent'"
        >
          <Icon name="check-circle" class="h-3 w-3" />
        </span>
        <span class="min-w-0 flex-1">
          <strong class="block text-[13px] font-semibold text-ink">{{ shift.name }}</strong>
          <span class="mt-0.5 block text-[12.5px] tabular-nums text-ink-soft">
            {{ formatTime(shift.start_time) }} – {{ formatTime(shift.end_time) }}
          </span>
          <span class="mt-0.5 block text-[11px] text-ink-faint">
            {{ shift.days_of_week.map((day) => DAY_LABELS[day]).join(' · ') }}
          </span>
        </span>
      </button>
    </div>
    <EmptyState v-else icon="calendar" message="No shifts exist. Create one first." />
  </div>
</template>
