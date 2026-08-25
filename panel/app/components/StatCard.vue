<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    icon: string
    label: string
    value: string
    tone?: 'default' | 'dark' | 'sunken'
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
    class="flex min-w-0 items-center gap-2 p-2.5 min-[360px]:gap-3 min-[360px]:p-3.5"
    :class="{
      'surface-dark': tone === 'dark',
      'surface-flat': tone === 'default',
      'rounded-md bg-surface-sunken': tone === 'sunken',
    }"
  >
    <span
      class="grid h-9 w-9 flex-none place-items-center rounded-md min-[360px]:h-10 min-[360px]:w-10"
      :class="accentClasses"
    >
      <Icon :name="icon" class="h-[18px] w-[18px] min-[360px]:h-5 min-[360px]:w-5" />
    </span>
    <span class="min-w-0">
      <span
        class="block truncate text-[17px] font-bold leading-tight tabular-nums tracking-tight min-[360px]:text-[18px]"
        :class="tone === 'dark' ? 'text-ink-dark' : 'text-ink'"
      >{{ value }}</span>
      <span class="block text-[11.5px] font-medium leading-4 min-[360px]:text-[12px]" :class="tone === 'dark' ? 'text-ink-dark-soft' : 'text-ink-soft'">{{ label }}</span>
    </span>
  </div>
</template>
