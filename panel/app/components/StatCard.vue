<script setup lang="ts">
withDefaults(defineProps<{ icon: string; label: string; value: string; tone?: 'default' | 'dark' }>(), {
  tone: 'default',
})
</script>

<template>
  <div
    class="flex items-center gap-3.5 p-4"
    :class="tone === 'dark' ? 'surface-dark' : 'surface-flat'"
  >
    <span
      class="grid h-10 w-10 flex-none place-items-center rounded-md"
      :class="tone === 'dark' ? 'bg-primary-soft-dark text-primary' : 'bg-primary-soft text-primary-strong'"
    >
      <Icon :name="icon" class="h-5 w-5" />
    </span>
    <span class="min-w-0">
      <span
        class="block truncate text-[18px] font-bold leading-tight tabular-nums tracking-tight"
        :class="tone === 'dark' ? 'text-ink-dark' : 'text-ink'"
      >{{ value }}</span>
      <span class="block text-[12px] font-medium" :class="tone === 'dark' ? 'text-ink-dark-soft' : 'text-ink-soft'">{{ label }}</span>
    </span>
  </div>
</template>
