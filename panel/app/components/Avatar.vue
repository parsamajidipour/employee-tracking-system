<script setup lang="ts">
const props = withDefaults(
  defineProps<{ name: string; size?: 'sm' | 'md' | 'lg'; muted?: boolean }>(),
  { size: 'md', muted: false },
)

const initials = computed(() =>
  props.name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('') || '?',
)

const sizeClasses = computed(
  () =>
    ({
      sm: 'h-8 w-8 text-[11px]',
      md: 'h-10 w-10 text-[13px]',
      lg: 'h-12 w-12 text-[15px]',
    })[props.size],
)
</script>

<template>
  <span
    class="grid flex-none place-items-center rounded-full font-bold tracking-tight"
    :class="[sizeClasses, muted ? 'bg-surface-sunken text-ink-faint' : 'bg-primary-soft text-primary-strong']"
    :title="name"
    aria-hidden="true"
  >{{ initials }}</span>
</template>
