<script setup lang="ts">
withDefaults(defineProps<{ icon: string; label: string; value: string; tone?: 'default' | 'dark' }>(), {
  tone: 'default',
})
</script>

<template>
  <div
    class="flex items-center gap-3 p-3.5"
    :class="tone === 'dark' ? 'surface-dark' : 'surface-flat'"
  >
    <span
      class="grid h-9 w-9 flex-none place-items-center rounded-md"
      :class="tone === 'dark' ? 'bg-primary-soft-dark text-primary' : 'bg-primary-soft text-primary-strong'"
    >
      <Icon :name="icon" class="h-[18px] w-[18px]" />
    </span>
    <span class="min-w-0">
      <span
        class="block truncate text-[17px] font-bold leading-tight tabular-nums tracking-tight"
        :class="tone === 'dark' ? 'text-ink-dark' : 'text-ink'"
      >{{ value }}</span>
      <span class="block text-[11.5px] font-medium" :class="tone === 'dark' ? 'text-ink-dark-soft' : 'text-ink-soft'">{{ label }}</span>
    </span>
  </div>
</template>
