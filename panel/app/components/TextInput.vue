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
  icon?: string
}>()

const model = defineModel<string>()

const id = computed(() => `field-${(props.label ?? 'input').toLowerCase().replace(/[^a-z0-9]+/g, '-')}`)
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="mb-1.5 block text-[12px] font-medium text-ink-soft">{{ label }}</label>

    <div class="relative">
      <Icon v-if="icon" :name="icon" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-faint" />
      <input
        :id="id"
        :value="model"
        @input="model = ($event.target as HTMLInputElement).value"
        :type="type ?? 'text'"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :minlength="minlength"
        class="field disabled:opacity-50"
        :class="[error ? '!border-state-danger' : '', icon ? 'pl-9' : '']"
      />
    </div>
    <p v-if="error" class="mt-1.5 text-xs text-state-danger">{{ error }}</p>
    <p v-else-if="hint" class="mt-1.5 text-xs text-ink-faint">{{ hint }}</p>
  </div>
</template>
