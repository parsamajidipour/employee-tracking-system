<script setup lang="ts">
defineProps<{ icon: string; label: string; value: string }>()
</script>

<template>
  <div class="card flex items-center gap-3.5 p-4">
    <span class="grid h-11 w-11 flex-none place-items-center rounded-control bg-primary-soft text-primary-strong">
      <Icon :name="icon" class="h-5 w-5" />
    </span>
    <span class="min-w-0">
      <span class="block truncate text-[19px] font-bold leading-tight tabular-nums text-ink">{{ value }}</span>
      <span class="block text-xs font-medium text-ink-soft">{{ label }}</span>
    </span>
  </div>
</template>
