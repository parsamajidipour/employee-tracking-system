<script setup lang="ts">
withDefaults(defineProps<{ variant?: 'neutral' | 'success' | 'warning' | 'danger' }>(), {
  variant: 'neutral',
})
</script>

<template>
  <span
    class="pill"
    :class="{
      'bg-state-neutral-soft text-ink-soft': variant === 'neutral',
      'bg-state-success-soft text-state-success': variant === 'success',
      'bg-state-warning-soft text-state-warning': variant === 'warning',
      'bg-state-danger-soft text-state-danger': variant === 'danger',
    }"
  >
    <span
      class="h-1.5 w-1.5 flex-none rounded-full"
      :class="{
        'bg-state-neutral': variant === 'neutral',
        'bg-state-success': variant === 'success',
        'bg-state-warning': variant === 'warning',
        'bg-state-danger': variant === 'danger',
      }"
    ></span>
    <slot />
  </span>
</template>
