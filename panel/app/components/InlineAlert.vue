<script setup lang="ts">
withDefaults(defineProps<{ variant?: 'error' | 'success' | 'warning' | 'info' }>(), { variant: 'error' })

const ICON: Record<string, string> = {
  error: 'alert-triangle',
  success: 'check-circle',
  warning: 'alert-triangle',
  info: 'inbox',
}

const COLORS: Record<string, string> = {
  error: 'bg-state-danger-soft text-state-danger',
  success: 'bg-state-success-soft text-state-success',
  warning: 'bg-state-warning-soft text-state-warning',
  info: 'bg-primary-soft text-primary-strong',
}
</script>

<template>
  <div class="enter mb-4 flex items-start gap-2.5 rounded-md px-3.5 py-3 text-[13px]" :class="COLORS[variant]">
    <Icon :name="ICON[variant]!" class="mt-0.5 h-4 w-4 flex-none" />
    <div><slot /></div>
  </div>
</template>
