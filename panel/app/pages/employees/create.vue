<script setup lang="ts">
import type { ShiftTemplate } from '~/composables/useShiftTemplates'

const form = reactive({
  name: '',
  phone: '',
  email: '',
  password: '',
  is_active: true,
  shift_template_ids: [] as number[],
})

const { data: templatesData, loading: loadingShifts, load: loadTemplates } = useShiftTemplates()
const templates = computed<ShiftTemplate[]>(() => templatesData.value ?? [])

const error = ref<string | null>(null)
const submitting = ref(false)
const toast = useToast()
const { t } = useI18n()
const { refresh: refreshEmployees } = useEmployees()

const canSubmit = computed(
  () => form.name.trim() !== '' && form.phone.trim() !== '' && form.email.trim() !== '' && form.password.length >= 8,
)

onMounted(loadTemplates)

async function submit() {
  if (!canSubmit.value) {
    error.value = t('employees.create.requiredFields')
    return
  }

  error.value = null
  submitting.value = true
  try {
    await apiFetch('/api/v1/employees', {
      method: 'POST',
      body: {
        name: form.name,
        phone: form.phone,
        email: form.email,
        password: form.password,
        is_active: form.is_active,
        shift_template_ids: form.shift_template_ids,
      },
    })
    await refreshEmployees()
    toast.success(t('employees.create.created'))
    await navigateTo('/employees')
  } catch (err) {
    error.value = apiErrorMessage(err, t('employees.create.failed'))
    toast.error(error.value)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppShell :title="t('employees.create.title')" :subtitle="t('employees.create.subtitle')" back-to="/employees" full-bleed>
    <form class="flex h-full min-h-0 flex-col gap-3 overflow-y-auto p-3 sm:gap-4 sm:p-5" @submit.prevent="submit">
      <InlineAlert v-if="error" class="!mb-0 flex-none">{{ error }}</InlineAlert>

      <div class="grid flex-none grid-cols-1 items-start gap-3 sm:gap-4 lg:grid-cols-[minmax(0,460px)_minmax(0,1fr)]">
        <Card icon="user-circle" :title="t('employees.create.accountDetails')" :subtitle="t('employees.create.accountDetailsSubtitle')">
          <div class="space-y-3.5">
            <TextInput v-model="form.name" :label="t('common.name')" :placeholder="t('employees.placeholders.name')" required />
            <TextInput
              v-model="form.phone"
              :label="t('common.phone')"
              :placeholder="t('employees.placeholders.phone')"
              required
              :hint="t('employees.create.phoneHint')"
            />
            <TextInput
              v-model="form.email"
              type="email"
              :label="t('common.email')"
              :placeholder="t('employees.placeholders.email')"
              required
              :hint="t('employees.create.emailHint')"
            />
            <TextInput
              v-model="form.password"
              type="password"
              :label="t('common.password')"
              :placeholder="t('employees.placeholders.password')"
              required
              :minlength="8"
              autocomplete="new-password"
            />

            <div class="flex items-center justify-between gap-3 rounded-md bg-surface-sunken px-3.5 py-3">
              <div class="min-w-0">
                <p class="text-[13px] font-medium text-ink">{{ t('common.active') }}</p>
                <p class="text-[12px] text-ink-faint">{{ t('employees.create.inactiveHint') }}</p>
              </div>
              <Toggle v-model="form.is_active" />
            </div>
          </div>
        </Card>

        <Card
          icon="calendar"
          :title="t('employees.create.shiftAssignment')"
          :subtitle="form.shift_template_ids.length ? t('employees.create.selectedShifts', { count: form.shift_template_ids.length }) : t('employees.create.shiftOptional')"
        >
          <p class="mb-3.5 text-[12.5px] text-ink-soft">
            {{ t('employees.create.trackingExplanation') }}
          </p>
          <ShiftPicker v-model="form.shift_template_ids" :shifts="templates" :loading="loadingShifts" />
        </Card>
      </div>

      <div class="flex flex-none justify-end border-t border-hairline pt-3 sm:pt-4">
        <Button type="submit" class="w-full sm:w-auto" :loading="submitting">
          {{ submitting ? t('employees.create.creating') : t('employees.create.create') }}
        </Button>
      </div>
    </form>
  </AppShell>
</template>
