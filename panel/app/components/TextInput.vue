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
const copied = ref(false)
let copiedTimer: ReturnType<typeof setTimeout> | undefined

const inputType = computed(() => isPassword.value && passwordVisible.value ? 'text' : (props.type ?? 'text'))

function togglePasswordVisibility() {
  passwordVisible.value = !passwordVisible.value
}

async function copyPassword() {
  if (!model.value) return

  try {
    await navigator.clipboard.writeText(model.value)
    copied.value = true
    if (copiedTimer) clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => {
      copied.value = false
    }, 1600)
  } catch {
    copied.value = false
  }
}

onBeforeUnmount(() => {
  if (copiedTimer) clearTimeout(copiedTimer)
})
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
        :class="[error ? '!border-state-danger' : '', icon ? 'ps-9' : '', isPassword ? 'pe-[4.75rem]' : '']"
      />
      <div v-if="isPassword" class="absolute end-1 top-1/2 flex -translate-y-1/2 items-center gap-0.5">
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
        <button
          type="button"
          class="grid h-8 w-8 place-items-center rounded-sm transition-colors hover:bg-surface-sunken focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-40"
          :class="copied ? 'text-state-success' : 'text-ink-faint hover:text-ink'"
          :disabled="disabled || !model"
          :aria-label="copied ? t('common.passwordCopied') : t('common.copyPassword')"
          :title="copied ? t('common.copied') : t('common.copyPassword')"
          @click="copyPassword"
        >
          <Icon :name="copied ? 'check' : 'copy'" class="h-4 w-4" />
        </button>
      </div>
    </div>
    <span class="sr-only" aria-live="polite">{{ copied ? t('common.passwordCopied') : '' }}</span>
    <p v-if="error" class="mt-1.5 text-xs text-state-danger">{{ error }}</p>
    <p v-else-if="hint" class="mt-1.5 text-xs text-ink-faint">{{ hint }}</p>
  </div>
</template>
