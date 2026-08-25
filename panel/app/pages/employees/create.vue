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
const { refresh: refreshEmployees } = useEmployees()

const canSubmit = computed(
  () => form.name.trim() !== '' && form.phone.trim() !== '' && form.email.trim() !== '' && form.password.length >= 8,
)

onMounted(loadTemplates)

async function submit() {
  if (!canSubmit.value) {
    error.value = 'Name, phone, email and a password of at least 8 characters are required.'
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
    toast.success('Employee created. A welcome email was sent with their login details.')
    await navigateTo('/employees')
  } catch (err) {
    error.value = apiErrorMessage(err, 'Save failed — check the fields (phone and email must be unique, password at least 8 characters).')
    toast.error(error.value)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppShell title="Add employee" subtitle="Create an account and give it a working schedule" back-to="/employees" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" to="/employees">Cancel</Button>
      <Button size="sm" :loading="submitting" @click="submit">
        {{ submitting ? 'Creating…' : 'Create employee' }}
      </Button>
    </template>

    <form class="flex h-full min-h-0 flex-col gap-4 overflow-y-auto p-4 sm:p-5" @submit.prevent="submit">
      <InlineAlert v-if="error" class="!mb-0 flex-none">{{ error }}</InlineAlert>

      <div class="grid flex-none grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(0,460px)_minmax(0,1fr)]">
        <Card icon="user-circle" title="Account details" subtitle="How this person signs in on the mobile app">
          <div class="space-y-3.5">
            <TextInput v-model="form.name" label="Name" placeholder="e.g. Ahmed Al Saadi" required />
            <TextInput
              v-model="form.phone"
              label="Phone"
              placeholder="e.g. 92000001"
              required
              hint="Used to log in on the mobile app — must be unique."
            />
            <TextInput
              v-model="form.email"
              type="email"
              label="Email"
              placeholder="name@example.com"
              required
              hint="Used to log in, and to send the welcome email."
            />
            <TextInput
              v-model="form.password"
              type="password"
              label="Password"
              placeholder="At least 8 characters"
              required
              :minlength="8"
              autocomplete="new-password"
            />

            <div class="flex items-center justify-between gap-3 rounded-md bg-surface-sunken px-3.5 py-3">
              <div class="min-w-0">
                <p class="text-[13px] font-medium text-ink">Active</p>
                <p class="text-[12px] text-ink-faint">Inactive accounts cannot sign in or be assigned a case.</p>
              </div>
              <Toggle v-model="form.is_active" />
            </div>
          </div>
        </Card>

        <Card
          icon="calendar"
          title="Shift assignment"
          :subtitle="form.shift_template_ids.length ? `${form.shift_template_ids.length} selected` : 'Optional — can be set later'"
        >
          <p class="mb-3.5 text-[12.5px] text-ink-soft">
            Location is only ever recorded inside a selected shift window. Leave every shift unselected and this
            employee is simply never tracked.
          </p>
          <ShiftPicker v-model="form.shift_template_ids" :shifts="templates" :loading="loadingShifts" />
        </Card>
      </div>
    </form>
  </AppShell>
</template>
