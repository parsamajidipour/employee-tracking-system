<script setup lang="ts">
defineProps<{ tabs: { value: string; label: string }[] }>()
const active = defineModel<string>({ required: true })
</script>

<template>
  <div class="inline-flex items-center gap-1 rounded-md bg-surface-sunken p-1">
    <button
      v-for="tab in tabs"
      :key="tab.value"
      type="button"
      class="relative h-7 rounded-sm px-3 text-[12.5px] font-medium transition-colors duration-fast ease-soft"
      :class="active === tab.value ? 'bg-surface text-ink shadow-ambient' : 'text-ink-soft hover:text-ink'"
      @click="active = tab.value"
    >
      {{ tab.label }}
    </button>
  </div>
</template>
