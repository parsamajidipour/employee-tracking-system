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
    <p v-if="loading" class="text-sm text-ink-faint">Loading…</p>
    <div v-else-if="shifts.length" class="grid gap-3 sm:grid-cols-2">
      <button
        v-for="shift in shifts"
        :key="shift.id"
        type="button"
        class="flex min-h-20 items-center gap-3.5 rounded-control border bg-surface p-3.5 text-left transition-colors"
        :class="selected.includes(shift.id) ? 'border-primary bg-primary-soft' : 'border-hairline hover:border-primary'"
        @click="toggle(shift.id)"
      >
        <span
          class="grid h-6 w-6 flex-none place-items-center rounded-small border text-sm font-bold"
          :class="selected.includes(shift.id) ? 'border-primary bg-primary text-white' : 'border-hairline'"
        >
          {{ selected.includes(shift.id) ? '✓' : '' }}
        </span>
        <span class="min-w-0 flex-1">
          <strong class="block text-sm text-ink">{{ shift.name }}</strong>
          <span class="mt-0.5 block text-sm tabular-nums text-ink-soft">
            {{ formatTime(shift.start_time) }} – {{ formatTime(shift.end_time) }}
          </span>
          <span class="mt-0.5 block text-xs text-ink-faint">
            {{ shift.days_of_week.map((day) => DAY_LABELS[day]).join(' · ') }}
          </span>
        </span>
      </button>
    </div>
    <p v-else class="rounded-control border border-hairline bg-surface p-5 text-sm text-ink-soft">
      No shifts exist. Create one first.
    </p>
  </div>
</template>
