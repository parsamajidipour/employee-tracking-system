<script setup lang="ts">
withDefaults(defineProps<{ variant?: 'error' | 'success' }>(), { variant: 'error' })
</script>

<template>
  <div
    class="mb-4 flex items-start gap-3 rounded-control px-4 py-3 text-sm"
    :class="variant === 'error' ? 'bg-state-danger/10 text-state-danger' : 'bg-state-success/10 text-state-success'"
  >
    <span
      class="mt-1.5 inline-block h-2 w-2 flex-none rounded-full"
      :class="variant === 'error' ? 'bg-state-danger' : 'bg-state-success'"
    ></span>
    <div><slot /></div>
  </div>
</template>
