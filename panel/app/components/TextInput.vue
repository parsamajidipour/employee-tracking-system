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

// Derived from the label text, not a random id: stable across server and
// client render so hydration never mismatches, unlike Math.random().
const id = computed(() => `field-${(props.label ?? 'input').toLowerCase().replace(/[^a-z0-9]+/g, '-')}`)
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="mb-1 block text-xs font-medium text-slate-500">{{ label }}</label>
    <!-- Explicit :value/@input, not v-model: Vue's v-model directive
         auto-casts via Number() for type="number" inputs, and Number('')
         is 0, not NaN — clearing the field would silently write 0 into
         this string-typed model instead of ''. Callers that need
         null-on-empty (e.g. max_daily_minutes) depend on actually
         seeing ''. -->
    <input
      :id="id"
      :value="model"
      @input="model = ($event.target as HTMLInputElement).value"
      :type="type ?? 'text'"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :minlength="minlength"
      class="w-full rounded border px-2.5 py-1.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 disabled:bg-slate-50 disabled:text-slate-400"
      :class="error ? 'border-red-400 focus:ring-red-600/30' : 'border-slate-300 focus:border-blue-600 focus:ring-blue-600/20'"
    />
    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    <p v-else-if="hint" class="mt-1 text-xs text-slate-400">{{ hint }}</p>
  </div>
</template>
