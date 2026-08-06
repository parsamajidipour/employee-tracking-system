<script setup lang="ts">
// Status tags only — neutral/success/warning/danger are semantic, not the
// app's single interactive accent. See DECISIONS.md-style reasoning in the
// design pass: accent (blue) is reserved for interactive elements and the
// map's "fresh" marker state, not for badges like this one.
withDefaults(defineProps<{ variant?: 'neutral' | 'success' | 'warning' | 'danger' }>(), { variant: 'neutral' })
</script>

<template>
  <span
    class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium"
    :class="{
      'bg-slate-100 text-slate-600': variant === 'neutral',
      'bg-emerald-50 text-emerald-700': variant === 'success',
      'bg-amber-50 text-amber-700': variant === 'warning',
      'bg-red-50 text-red-700': variant === 'danger',
    }"
  >
    <slot />
  </span>
</template>
