<script setup lang="ts">
withDefaults(defineProps<{ variant?: 'error' | 'success' | 'warning' | 'info' }>(), { variant: 'error' })

const COLORS: Record<string, string> = {
  error: 'bg-state-danger/10 text-state-danger',
  success: 'bg-state-success/10 text-state-success',
  warning: 'bg-state-warning/10 text-state-warning',
  info: 'bg-primary-soft text-primary-strong',
}

const DOTS: Record<string, string> = {
  error: 'bg-state-danger',
  success: 'bg-state-success',
  warning: 'bg-state-warning',
  info: 'bg-primary',
}
</script>

<template>
  <div class="mb-4 flex items-start gap-3 rounded-control px-4 py-3 text-sm" :class="COLORS[variant]">
    <span class="mt-1.5 inline-block h-2 w-2 flex-none rounded-full" :class="DOTS[variant]"></span>
    <div><slot /></div>
  </div>
</template>
