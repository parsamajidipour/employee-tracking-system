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
  autocomplete?: string
}>()

const model = defineModel<string>()
const { t } = useI18n()

const id = computed(() => `field-${(props.label ?? 'input').toLowerCase().replace(/[^a-z0-9]+/g, '-')}`)
const isPassword = computed(() => props.type === 'password')
const passwordVisible = ref(false)

const inputType = computed(() => isPassword.value && passwordVisible.value ? 'text' : (props.type ?? 'text'))

function togglePasswordVisibility() {
  passwordVisible.value = !passwordVisible.value
}
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="mb-1.5 block text-[12px] font-medium text-ink-soft">{{ label }}</label>

    <div class="relative">
      <Icon v-if="icon" :name="icon" class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-faint" />
      <input
        :id="id"
        :value="model"
        @input="model = ($event.target as HTMLInputElement).value"
        :type="inputType"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :minlength="minlength"
        :autocomplete="autocomplete"
        class="field disabled:opacity-50"
        :class="[error ? '!border-state-danger' : '', icon ? 'ps-9' : '', isPassword ? 'pe-11' : '']"
      />
      <div v-if="isPassword" class="absolute end-1 top-1/2 -translate-y-1/2">
        <button
          type="button"
          class="grid h-8 w-8 place-items-center rounded-sm text-ink-faint transition-colors hover:bg-surface-sunken hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="disabled"
          :aria-label="passwordVisible ? t('common.hidePassword') : t('common.showPassword')"
          :aria-pressed="passwordVisible"
          :title="passwordVisible ? t('common.hidePassword') : t('common.showPassword')"
          @click="togglePasswordVisibility"
        >
          <Icon :name="passwordVisible ? 'eye-off' : 'eye'" class="h-4 w-4" />
        </button>
      </div>
    </div>
    <p v-if="error" class="mt-1.5 text-xs text-state-danger">{{ error }}</p>
    <p v-else-if="hint" class="mt-1.5 text-xs text-ink-faint">{{ hint }}</p>
  </div>
</template>
