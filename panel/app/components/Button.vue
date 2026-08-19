<script setup lang="ts">
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
  'inline-flex items-center justify-center gap-2 rounded-control font-semibold transition-all duration-150 ease-soft active:scale-[0.97]',
  props.size === 'sm' ? 'min-h-[36px] px-3 text-sm' : 'min-h-[44px] px-5 text-sm',
  props.disabled ? 'cursor-not-allowed opacity-60 active:scale-100' : '',
  props.variant === 'primary' ? 'bg-primary text-white hover:bg-primary-strong' : '',
  props.variant === 'secondary' ? 'border border-hairline bg-surface text-primary-strong hover:bg-surface-muted' : '',
  props.variant === 'danger' ? 'bg-state-danger text-white hover:opacity-90' : '',
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
