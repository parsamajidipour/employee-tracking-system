<script setup lang="ts">
// A page-level error/success banner that reads as one — a bordered,
// tinted box with a status dot, not red body text. Used for persistent
// page state (e.g. "failed to load"); transient action feedback goes
// through useToast() instead.
withDefaults(defineProps<{ variant?: 'error' | 'success' }>(), { variant: 'error' })
</script>

<template>
  <div
    class="mb-4 flex items-start gap-2 rounded border px-3 py-2 text-sm"
    :class="variant === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
  >
    <span
      class="mt-1.5 inline-block h-1.5 w-1.5 flex-none rounded-full"
      :class="variant === 'error' ? 'bg-red-600' : 'bg-emerald-600'"
    ></span>
    <div><slot /></div>
  </div>
</template>
