<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
    size?: 'sm' | 'md'
    type?: 'button' | 'submit'
    disabled?: boolean
    to?: string
  }>(),
  { variant: 'primary', size: 'md', type: 'button', disabled: false },
)

const classes = computed(() => [
  'btn font-semibold',
  props.size === 'sm' ? 'h-8 px-2.5 text-xs' : '',
  props.disabled ? 'cursor-not-allowed opacity-50 pointer-events-none' : '',
  {
    'bg-primary text-white shadow-ambient hover:bg-primary-strong': props.variant === 'primary',
    'bg-surface text-ink border border-hairline shadow-ambient hover:bg-surface-sunken': props.variant === 'secondary',
    'text-ink-soft hover:bg-surface-sunken hover:text-ink': props.variant === 'ghost',
    'bg-state-danger text-white hover:opacity-90': props.variant === 'danger',
  },
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
