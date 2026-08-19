<script setup lang="ts">
withDefaults(defineProps<{ rounded?: 'sm' | 'md' | 'pill' }>(), { rounded: 'sm' })
</script>

<template>
  <div
    class="animate-pulse bg-surface-sunken"
    :class="{
      'rounded-sm': rounded === 'sm',
      'rounded-md': rounded === 'md',
      'rounded-pill': rounded === 'pill',
    }"
  ></div>
</template>
