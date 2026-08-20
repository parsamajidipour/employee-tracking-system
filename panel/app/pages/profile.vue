<script setup lang="ts">
const { user, refresh } = useAuthUser()
const toast = useToast()
const refreshing = ref(false)

const infoSaving = ref(false)
const infoError = ref<string | null>(null)
const infoForm = reactive({ name: '', email: '' })

const passwordSaving = ref(false)
const passwordError = ref<string | null>(null)
const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

watchEffect(() => {
  if (!user.value) return
  infoForm.name = user.value.name
  infoForm.email = user.value.email
})

async function refreshProfile() {
  refreshing.value = true
  try {
    await refresh()
  } finally {
    refreshing.value = false
  }
}

async function submitInfo() {
  infoSaving.value = true
  infoError.value = null
  try {
    await apiFetch('/api/v1/admin/profile', {
      method: 'PUT',
      body: { name: infoForm.name, email: infoForm.email },
    })
    await refresh()
    toast.success('Profile updated.')
  } catch (err) {
    infoError.value = apiErrorMessage(err, 'Update failed. Check the email address and try again.')
  } finally {
    infoSaving.value = false
  }
}

async function submitPassword() {
  passwordSaving.value = true
  passwordError.value = null
  try {
    await apiFetch('/api/v1/admin/password', {
      method: 'PUT',
      body: {
        current_password: passwordForm.current_password,
        password: passwordForm.password,
        password_confirmation: passwordForm.password_confirmation,
      },
    })
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
    toast.success('Password changed.')
  } catch (err) {
    passwordError.value = apiErrorMessage(err, 'Change failed. Check the current password and password requirements.')
  } finally {
    passwordSaving.value = false
  }
}

onMounted(refreshProfile)
</script>

<template>
  <AppShell title="Admin profile">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="refreshing" @click="refreshProfile">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="refreshing" />
        Refresh
      </Button>
    </template>

    <div class="grid gap-5 lg:grid-cols-2 lg:items-start">
      <form class="surface-flat space-y-4 p-5" @submit.prevent="submitInfo">
        <div>
          <h2 class="text-[15px] font-semibold text-ink">Profile</h2>
          <p class="mt-0.5 text-[12.5px] text-ink-faint">Your name and email address.</p>
        </div>

        <InlineAlert v-if="infoError">{{ infoError }}</InlineAlert>
        <TextInput v-model="infoForm.name" label="Name" required autocomplete="name" />
        <TextInput v-model="infoForm.email" type="email" label="Email" required autocomplete="email" />

        <Button type="submit" :disabled="infoSaving">{{ infoSaving ? 'Saving…' : 'Save profile' }}</Button>
      </form>

      <form class="surface-flat space-y-4 p-5" @submit.prevent="submitPassword">
        <div>
          <h2 class="text-[15px] font-semibold text-ink">Change password</h2>
          <p class="mt-0.5 text-[12.5px] text-ink-faint">Choose a new password for your admin account.</p>
        </div>

        <InlineAlert v-if="passwordError">{{ passwordError }}</InlineAlert>
        <TextInput v-model="passwordForm.current_password" type="password" label="Current password" required autocomplete="current-password" />
        <TextInput v-model="passwordForm.password" type="password" label="New password" required :minlength="10" autocomplete="new-password" />
        <TextInput v-model="passwordForm.password_confirmation" type="password" label="Confirm new password" required :minlength="10" autocomplete="new-password" />

        <Button type="submit" :disabled="passwordSaving">{{ passwordSaving ? 'Changing…' : 'Change password' }}</Button>
      </form>
    </div>
  </AppShell>
</template>
