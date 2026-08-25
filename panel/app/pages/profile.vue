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

const ROLE_LABEL: Record<string, string> = {
  admin: 'Administrator',
  hr: 'HR',
  supervisor: 'Supervisor',
  employee: 'Employee',
}

const roleLabel = computed(() => (user.value ? ROLE_LABEL[user.value.role] ?? user.value.role : '—'))

const infoDirty = computed(
  () => !!user.value && (infoForm.name !== user.value.name || infoForm.email !== user.value.email),
)

const passwordReady = computed(
  () =>
    passwordForm.current_password !== '' &&
    passwordForm.password.length >= 10 &&
    passwordForm.password === passwordForm.password_confirmation,
)

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
    toast.error(infoError.value)
  } finally {
    infoSaving.value = false
  }
}

async function submitPassword() {
  if (passwordForm.password !== passwordForm.password_confirmation) {
    passwordError.value = 'The new password and its confirmation do not match.'
    return
  }

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
    toast.error(passwordError.value)
  } finally {
    passwordSaving.value = false
  }
}

async function signOut() {
  try {
    await apiFetch('/api/logout', { method: 'POST' })
  } catch {
  }
  await navigateTo('/login')
}

onMounted(refreshProfile)
</script>

<template>
  <AppShell title="Admin profile" subtitle="Your account details and sign-in credentials" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="refreshing" aria-label="Refresh profile" @click="refreshProfile">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="refreshing" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
    </template>

    <div class="h-full min-h-0 overflow-y-auto p-4 sm:p-5">
      <div class="mx-auto grid max-w-5xl grid-cols-1 items-start gap-4 lg:grid-cols-2">
        <Card class="lg:col-span-2" icon="user-circle" title="Signed in as" :subtitle="user?.email ?? 'Loading…'">
          <template #actions>
            <Button variant="secondary" size="sm" @click="signOut">Sign out</Button>
          </template>

          <div class="flex flex-wrap items-center gap-4">
            <Avatar :name="user?.name ?? '?'" size="lg" />
            <dl class="grid flex-1 grid-cols-2 gap-x-5 gap-y-3 text-[13px] sm:grid-cols-3">
              <div>
                <dt class="eyebrow mb-1">Name</dt>
                <dd class="truncate text-ink">{{ user?.name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="eyebrow mb-1">Email</dt>
                <dd class="truncate text-ink">{{ user?.email ?? '—' }}</dd>
              </div>
              <div>
                <dt class="eyebrow mb-1">Role</dt>
                <dd><Badge variant="success">{{ roleLabel }}</Badge></dd>
              </div>
            </dl>
          </div>
        </Card>

        <Card icon="pencil" title="Profile details" subtitle="Shown across the panel and on audit entries">
          <form class="space-y-3.5" @submit.prevent="submitInfo">
            <InlineAlert v-if="infoError" class="!mb-0">{{ infoError }}</InlineAlert>

            <TextInput
              v-model="infoForm.name"
              label="Name"
              placeholder="Your full name"
              required
              autocomplete="name"
            />
            <TextInput
              v-model="infoForm.email"
              type="email"
              label="Email"
              placeholder="you@example.com"
              required
              autocomplete="email"
              hint="You sign in with this address."
            />

            <div class="flex items-center gap-2 border-t border-hairline pt-3.5">
              <Button type="submit" :disabled="!infoDirty" :loading="infoSaving">
                {{ infoSaving ? 'Saving…' : 'Save profile' }}
              </Button>
              <span v-if="!infoDirty" class="text-[12px] text-ink-faint">No changes to save.</span>
            </div>
          </form>
        </Card>

        <Card icon="lock" title="Change password" subtitle="Signs out every other session using this account">
          <form class="space-y-3.5" @submit.prevent="submitPassword">
            <InlineAlert v-if="passwordError" class="!mb-0">{{ passwordError }}</InlineAlert>

            <TextInput
              v-model="passwordForm.current_password"
              type="password"
              label="Current password"
              placeholder="Your password right now"
              required
              autocomplete="current-password"
            />
            <TextInput
              v-model="passwordForm.password"
              type="password"
              label="New password"
              placeholder="At least 10 characters"
              required
              :minlength="10"
              autocomplete="new-password"
            />
            <TextInput
              v-model="passwordForm.password_confirmation"
              type="password"
              label="Confirm new password"
              placeholder="Repeat the new password"
              required
              :minlength="10"
              autocomplete="new-password"
              :error="passwordForm.password_confirmation !== '' && passwordForm.password !== passwordForm.password_confirmation
                ? 'These two passwords do not match.'
                : null"
            />

            <div class="flex items-center gap-2 border-t border-hairline pt-3.5">
              <Button type="submit" :disabled="!passwordReady" :loading="passwordSaving">
                {{ passwordSaving ? 'Changing…' : 'Change password' }}
              </Button>
            </div>
          </form>
        </Card>
      </div>
    </div>
  </AppShell>
</template>
