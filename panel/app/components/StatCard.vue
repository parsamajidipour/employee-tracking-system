<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    icon: string
    label: string
    value: string
    tone?: 'default' | 'dark'
    accent?: 'primary' | 'success' | 'warning' | 'danger' | 'neutral'
  }>(),
  { tone: 'default', accent: 'primary' },
)

const accentClasses = computed(() => {
  if (props.tone === 'dark') return 'bg-primary-soft-dark text-primary'
  return {
    primary: 'bg-primary-soft text-primary-strong',
    success: 'bg-state-success-soft text-state-success',
    warning: 'bg-state-warning-soft text-state-warning',
    danger: 'bg-state-danger-soft text-state-danger',
    neutral: 'bg-state-neutral-soft text-ink-soft',
  }[props.accent]
})
</script>

<template>
  <div
    class="flex items-center gap-3.5 p-4"
    :class="tone === 'dark' ? 'surface-dark' : 'surface-flat'"
  >
    <span
      class="grid h-10 w-10 flex-none place-items-center rounded-md"
      :class="accentClasses"
    >
      <Icon :name="icon" class="h-5 w-5" />
    </span>
    <span class="min-w-0">
      <span
        class="block truncate text-[18px] font-bold leading-tight tabular-nums tracking-tight"
        :class="tone === 'dark' ? 'text-ink-dark' : 'text-ink'"
      >{{ value }}</span>
      <span class="block text-[12px] font-medium" :class="tone === 'dark' ? 'text-ink-dark-soft' : 'text-ink-soft'">{{ label }}</span>
    </span>
  </div>
</template>
