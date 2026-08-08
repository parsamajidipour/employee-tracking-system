<script setup lang="ts">
const props = defineProps<{
  label?: string
  error?: string | null
  required?: boolean
}>()

const model = defineModel<string | number | null>()

const id = computed(() => `field-${(props.label ?? 'select').toLowerCase().replace(/[^a-z0-9]+/g, '-')}`)
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="mb-1 block text-xs font-medium text-ink-soft">{{ label }}</label>
    <select
      :id="id"
      v-model="model"
      :required="required"
      class="w-full rounded border bg-surface px-2.5 py-1.5 text-sm text-ink focus:outline-none focus:ring-2"
      :class="error ? 'border-state-danger focus:ring-state-danger/30' : 'border-hairline focus:border-primary focus:ring-primary/20'"
    >
      <slot />
    </select>
    <p v-if="error" class="mt-1 text-xs text-state-danger">{{ error }}</p>
  </div>
</template>
