<script setup lang="ts">
withDefaults(defineProps<{ variant?: 'neutral' | 'success' | 'warning' | 'danger' }>(), {
  variant: 'neutral',
})
</script>

<template>
  <span
    class="pill"
    :class="{
      'bg-state-neutral/12 text-state-neutral': variant === 'neutral',
      'bg-state-success/12 text-state-success': variant === 'success',
      'bg-state-warning/12 text-state-warning': variant === 'warning',
      'bg-state-danger/12 text-state-danger': variant === 'danger',
    }"
  >
    <span
      class="h-2 w-2 flex-none rounded-full"
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
