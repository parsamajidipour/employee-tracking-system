<script setup lang="ts">
// Local button component — no UI library. `to` renders a NuxtLink styled
// identically to a real <button>, for header actions that navigate
// (e.g. "Add employee") rather than submit/click-handle in place.
const props = withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'danger'
    size?: 'sm' | 'md'
    type?: 'button' | 'submit'
    disabled?: boolean
    to?: string
  }>(),
  { variant: 'primary', size: 'md', type: 'button', disabled: false },
)

const classes = computed(() => [
  'inline-flex items-center justify-center rounded font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-1',
  props.size === 'sm' ? 'px-2 py-1 text-xs' : 'px-3 py-1.5 text-sm',
  props.disabled ? 'cursor-not-allowed opacity-50' : '',
  props.variant === 'primary' ? 'bg-blue-600 text-white hover:bg-blue-700 focus-visible:ring-blue-600' : '',
  props.variant === 'secondary' ? 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus-visible:ring-slate-400' : '',
  props.variant === 'danger' ? 'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-600' : '',
])
</script>

<template>
  <NuxtLink v-if="to" :to="to" :class="classes">
    <slot />
  </NuxtLink>
  <button v-else :type="type" :disabled="disabled" :class="classes">
    <slot />
  </button>
</template>
