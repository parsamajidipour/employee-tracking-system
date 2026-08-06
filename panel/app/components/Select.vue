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
    <label v-if="label" :for="id" class="mb-1 block text-xs font-medium text-slate-500">{{ label }}</label>
    <select
      :id="id"
      v-model="model"
      :required="required"
      class="w-full rounded border bg-white px-2.5 py-1.5 text-sm text-slate-900 focus:outline-none focus:ring-2"
      :class="error ? 'border-red-400 focus:ring-red-600/30' : 'border-slate-300 focus:border-blue-600 focus:ring-blue-600/20'"
    >
      <slot />
    </select>
    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
  </div>
</template>
