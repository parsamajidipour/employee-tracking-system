<script setup lang="ts">
const props = defineProps<{
  label?: string
  error?: string | null
  type?: string
  placeholder?: string
  required?: boolean
  disabled?: boolean
  minlength?: number
  hint?: string
}>()

const model = defineModel<string>()

const id = computed(() => `field-${(props.label ?? 'input').toLowerCase().replace(/[^a-z0-9]+/g, '-')}`)
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="mb-1.5 block text-[13px] font-medium text-ink-soft">{{ label }}</label>

    <input
      :id="id"
      :value="model"
      @input="model = ($event.target as HTMLInputElement).value"
      :type="type ?? 'text'"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :minlength="minlength"
      class="field disabled:opacity-60"
      :class="error ? '!border-state-danger' : ''"
    />
    <p v-if="error" class="mt-1.5 text-xs text-state-danger">{{ error }}</p>
    <p v-else-if="hint" class="mt-1.5 text-xs text-ink-faint">{{ hint }}</p>
  </div>
</template>
