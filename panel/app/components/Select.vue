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
    <label v-if="label" :for="id" class="mb-1.5 block text-[12px] font-medium text-ink-soft">{{ label }}</label>
    <div class="relative">
      <select
        :id="id"
        v-model="model"
        :required="required"
        class="field appearance-none pr-9"
        :class="error ? '!border-state-danger' : ''"
      >
        <slot />
      </select>
      <Icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-ink-faint" />
    </div>
    <p v-if="error" class="mt-1.5 text-xs text-state-danger">{{ error }}</p>
  </div>
</template>
