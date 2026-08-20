<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
    size?: 'sm' | 'md'
    type?: 'button' | 'submit'
    disabled?: boolean
    loading?: boolean
    to?: string
  }>(),
  { variant: 'primary', size: 'md', type: 'button', disabled: false, loading: false },
)

const isDisabled = computed(() => props.disabled || props.loading)

const classes = computed(() => [
  'btn font-semibold transition-shadow',
  props.size === 'sm' ? 'h-10 px-3.5 text-[13.5px]' : '',
  isDisabled.value ? 'cursor-not-allowed opacity-50 pointer-events-none' : '',
  {
    'bg-primary text-white shadow-ambient hover:bg-primary-strong hover:shadow-key': props.variant === 'primary',
    'bg-surface text-ink border border-hairline shadow-ambient hover:border-ink-faint/40 hover:bg-surface-sunken hover:shadow-key': props.variant === 'secondary',
    'text-ink-soft hover:bg-surface-sunken hover:text-ink': props.variant === 'ghost',
    'bg-state-danger text-white shadow-ambient hover:opacity-90 hover:shadow-key': props.variant === 'danger',
  },
])
</script>

<template>
  <NuxtLink v-if="to" :to="to" :class="classes" :aria-disabled="isDisabled">
    <Icon v-if="loading" name="refresh" class="h-3.5 w-3.5" spin />
    <slot />
  </NuxtLink>
  <button v-else :type="type" :disabled="isDisabled" :class="classes">
    <Icon v-if="loading" name="refresh" class="h-3.5 w-3.5" spin />
    <slot />
  </button>
</template>
