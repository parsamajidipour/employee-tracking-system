<script setup lang="ts">
const props = withDefaults(defineProps<{
  label?: string
  placeholder?: string
  required?: boolean
  disabled?: boolean
}>(), {
  required: false,
  disabled: false,
})

const model = defineModel<string>({ default: '' })
const { t, locale } = useI18n()
const open = ref(false)
const inputId = useId()
const draftHour = ref(7)
const draftMinute = ref(0)
const hours = Array.from({ length: 24 }, (_, value) => value)
const minutes = Array.from({ length: 60 }, (_, value) => value)

function parseTime(value?: string): [number, number] | null {
  const match = value?.match(/^(\d{1,2}):(\d{2})/)
  if (!match) return null
  const hour = Number(match[1])
  const minute = Number(match[2])
  return hour >= 0 && hour <= 23 && minute >= 0 && minute <= 59 ? [hour, minute] : null
}

function formatTime(hour: number, minute: number): string {
  return new Intl.DateTimeFormat(locale.value, { hour: 'numeric', minute: '2-digit' })
    .format(new Date(2024, 0, 1, hour, minute))
}

function formatHour(hour: number): string {
  return new Intl.DateTimeFormat(locale.value, { hour: 'numeric' })
    .format(new Date(2024, 0, 1, hour))
}

const displayValue = computed(() => {
  const parts = parseTime(model.value)
  return parts ? formatTime(parts[0], parts[1]) : ''
})

function openPicker() {
  if (props.disabled) return
  const selected = parseTime(model.value) ?? [7, 0]
  draftHour.value = selected[0]
  draftMinute.value = selected[1]
  open.value = true
}

function confirmTime() {
  model.value = `${String(draftHour.value).padStart(2, '0')}:${String(draftMinute.value).padStart(2, '0')}`
  open.value = false
}
</script>

<template>
  <div class="min-w-0 max-w-full">
    <label v-if="label" :id="`${inputId}-label`" class="mb-1.5 block text-[12px] font-medium text-ink-soft">{{ label }}</label>
    <button
      :id="inputId"
      type="button"
      class="field flex min-w-0 items-center gap-3 text-start disabled:cursor-not-allowed disabled:opacity-50"
      :disabled="disabled"
      :aria-labelledby="label ? `${inputId}-label` : undefined"
      aria-haspopup="dialog"
      @click="openPicker"
    >
      <span class="min-w-0 flex-1 truncate tabular" :class="displayValue ? 'text-ink' : 'text-ink-faint'">{{ displayValue || placeholder || t('common.selectTime') }}</span>
      <Icon name="clock" class="h-4 w-4 flex-none text-ink-faint" />
    </button>

    <Modal v-model="open" :title="label || t('common.selectTime')">
      <div class="grid grid-cols-2 gap-3">
        <Select v-model="draftHour" :label="t('common.hour')">
          <option v-for="hour in hours" :key="hour" :value="hour">{{ formatHour(hour) }}</option>
        </Select>
        <Select v-model="draftMinute" :label="t('common.minute')">
          <option v-for="minute in minutes" :key="minute" :value="minute">{{ String(minute).padStart(2, '0') }}</option>
        </Select>
      </div>
      <template #footer>
        <Button type="button" @click="confirmTime">{{ t('common.done') }}</Button>
      </template>
    </Modal>
  </div>
</template>
