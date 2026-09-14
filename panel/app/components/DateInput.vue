<script setup lang="ts">
const props = withDefaults(defineProps<{
  label?: string
  placeholder?: string
  min?: string
  max?: string
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
const now = new Date()
const draftYear = ref(now.getFullYear())
const draftMonth = ref(now.getMonth() + 1)
const draftDay = ref(now.getDate())

function parseIso(value?: string): [number, number, number] | null {
  const match = value?.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (!match) return null
  return [Number(match[1]), Number(match[2]), Number(match[3])]
}

function isoDate(year: number, month: number, day: number): string {
  return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

const minParts = computed(() => parseIso(props.min))
const maxParts = computed(() => parseIso(props.max))
const years = computed(() => {
  const first = minParts.value?.[0] ?? now.getFullYear() - 50
  const last = maxParts.value?.[0] ?? now.getFullYear() + 5
  return Array.from({ length: Math.max(1, last - first + 1) }, (_, index) => last - index)
})
const months = computed(() => Array.from({ length: 12 }, (_, index) => ({
  value: index + 1,
  label: new Intl.DateTimeFormat(locale.value, { month: 'short' }).format(new Date(2024, index, 1)),
})).filter((month) => {
  if (minParts.value?.[0] === draftYear.value && month.value < minParts.value[1]) return false
  if (maxParts.value?.[0] === draftYear.value && month.value > maxParts.value[1]) return false
  return true
}))
const days = computed(() => {
  const monthLength = new Date(draftYear.value, draftMonth.value, 0).getDate()
  const first = minParts.value && minParts.value[0] === draftYear.value && minParts.value[1] === draftMonth.value ? minParts.value[2] : 1
  const last = maxParts.value && maxParts.value[0] === draftYear.value && maxParts.value[1] === draftMonth.value ? maxParts.value[2] : monthLength
  return Array.from({ length: Math.max(1, last - first + 1) }, (_, index) => first + index)
})

watch(days, (values) => {
  if (!values.includes(draftDay.value)) draftDay.value = values[values.length - 1]!
})

watch(months, (values) => {
  if (!values.some(month => month.value === draftMonth.value)) draftMonth.value = values[0]!.value
})

const displayValue = computed(() => {
  const parts = parseIso(model.value)
  if (!parts) return ''
  return new Intl.DateTimeFormat(locale.value, { year: 'numeric', month: 'short', day: 'numeric' })
    .format(new Date(parts[0], parts[1] - 1, parts[2]))
})

function openPicker() {
  if (props.disabled) return
  const selected = parseIso(model.value) ?? maxParts.value ?? [now.getFullYear(), now.getMonth() + 1, now.getDate()]
  draftYear.value = selected[0]
  draftMonth.value = selected[1]
  draftDay.value = selected[2]
  open.value = true
}

function confirmDate() {
  let value = isoDate(draftYear.value, draftMonth.value, draftDay.value)
  if (props.min && value < props.min) value = props.min
  if (props.max && value > props.max) value = props.max
  model.value = value
  open.value = false
}

function clearDate() {
  model.value = ''
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
      <span class="min-w-0 flex-1 truncate" :class="displayValue ? 'text-ink' : 'text-ink-faint'">{{ displayValue || placeholder || t('common.selectDate') }}</span>
      <Icon name="calendar" class="h-4 w-4 flex-none text-ink-faint" />
    </button>

    <Modal v-model="open" :title="label || t('common.selectDate')">
      <div class="grid grid-cols-3 gap-2.5">
        <Select v-model="draftDay" :label="t('common.day')">
          <option v-for="day in days" :key="day" :value="day">{{ day }}</option>
        </Select>
        <Select v-model="draftMonth" :label="t('common.month')">
          <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
        </Select>
        <Select v-model="draftYear" :label="t('common.year')">
          <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
        </Select>
      </div>
      <template #footer>
        <Button v-if="!required" type="button" variant="ghost" @click="clearDate">{{ t('common.clear') }}</Button>
        <Button type="button" @click="confirmDate">{{ t('common.done') }}</Button>
      </template>
    </Modal>
  </div>
</template>
