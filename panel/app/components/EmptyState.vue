<script setup lang="ts">
withDefaults(
  defineProps<{
    icon?: string
    message: string
    tone?: 'neutral' | 'danger'
  }>(),
  { icon: 'inbox', tone: 'neutral' },
)
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-2.5 py-2 text-center">
    <span
      class="grid h-10 w-10 place-items-center rounded-md"
      :class="tone === 'danger' ? 'bg-state-danger-soft text-state-danger' : 'bg-surface-sunken text-ink-faint'"
    >
      <Icon :name="icon" class="h-5 w-5" />
    </span>
    <p class="max-w-xs text-[13px]" :class="tone === 'danger' ? 'text-state-danger' : 'text-ink-soft'">{{ message }}</p>
    <div v-if="$slots.action" class="mt-1"><slot name="action" /></div>
  </div>
</template>
