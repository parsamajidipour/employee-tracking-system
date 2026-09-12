<script setup lang="ts">
const { user, refresh } = useAuthUser()
const toast = useToast()
const refreshing = ref(false)
const { t } = useI18n()

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

const roleLabel = computed(() => (user.value ? t(`profile.roles.${user.value.role}`) : '—'))

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
    toast.success(t('profile.updated'))
  } catch (err) {
    infoError.value = apiErrorMessage(err, t('profile.updateFailed'))
    toast.error(infoError.value)
  } finally {
    infoSaving.value = false
  }
}

async function submitPassword() {
  if (passwordForm.password !== passwordForm.password_confirmation) {
    passwordError.value = t('profile.passwordMismatch')
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
    toast.success(t('employees.list.passwordChanged'))
  } catch (err) {
    passwordError.value = apiErrorMessage(err, t('profile.passwordFailed'))
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
  <AppShell :title="t('nav.adminProfile')" :subtitle="t('profile.subtitle')" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="refreshing" :aria-label="t('profile.refresh')" @click="refreshProfile">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="refreshing" />
        <span class="hidden sm:inline">{{ t('common.refresh') }}</span>
      </Button>
    </template>

    <div class="h-full min-h-0 overflow-y-auto p-3 sm:p-5">
      <div class="mx-auto grid max-w-5xl grid-cols-1 items-start gap-3 sm:gap-4 lg:grid-cols-2">
        <Card class="lg:col-span-2" icon="user-circle" :title="t('profile.signedInAs')" :subtitle="user?.email ?? t('common.loading')">
          <template #actions>
            <Button variant="secondary" size="sm" @click="signOut">{{ t('nav.signOut') }}</Button>
          </template>

          <div class="flex flex-wrap items-center gap-4">
            <Avatar :name="user?.name ?? '?'" size="lg" />
            <dl class="grid min-w-0 flex-1 grid-cols-1 gap-x-5 gap-y-3 text-[13px] min-[360px]:grid-cols-2 sm:grid-cols-3">
              <div>
                <dt class="eyebrow mb-1">{{ t('common.name') }}</dt>
                <dd class="truncate text-ink">{{ user?.name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="eyebrow mb-1">{{ t('common.email') }}</dt>
                <dd class="truncate text-ink">{{ user?.email ?? '—' }}</dd>
              </div>
              <div>
                <dt class="eyebrow mb-1">{{ t('profile.role') }}</dt>
                <dd><Badge variant="success">{{ roleLabel }}</Badge></dd>
              </div>
            </dl>
          </div>
        </Card>

        <Card icon="pencil" :title="t('profile.details')" :subtitle="t('profile.detailsSubtitle')">
          <form class="space-y-3.5" @submit.prevent="submitInfo">
            <InlineAlert v-if="infoError" class="!mb-0">{{ infoError }}</InlineAlert>

            <TextInput
              v-model="infoForm.name"
              :label="t('common.name')"
              :placeholder="t('profile.fullName')"
              required
              autocomplete="name"
            />
            <TextInput
              v-model="infoForm.email"
              type="email"
              :label="t('common.email')"
              :placeholder="t('profile.emailPlaceholder')"
              required
              autocomplete="email"
              :hint="t('profile.emailHint')"
            />

            <div class="flex items-center gap-2 border-t border-hairline pt-3.5">
              <Button type="submit" :disabled="!infoDirty" :loading="infoSaving">
                {{ infoSaving ? t('common.saving') : t('profile.save') }}
              </Button>
              <span v-if="!infoDirty" class="text-[12px] text-ink-faint">{{ t('profile.noChanges') }}</span>
            </div>
          </form>
        </Card>

        <Card icon="lock" :title="t('employees.list.changePassword')" :subtitle="t('profile.passwordSubtitle')">
          <form class="space-y-3.5" @submit.prevent="submitPassword">
            <InlineAlert v-if="passwordError" class="!mb-0">{{ passwordError }}</InlineAlert>

            <TextInput
              v-model="passwordForm.current_password"
              type="password"
              :label="t('profile.currentPassword')"
              :placeholder="t('profile.currentPasswordPlaceholder')"
              required
              autocomplete="current-password"
            />
            <TextInput
              v-model="passwordForm.password"
              type="password"
              :label="t('employees.list.newPassword')"
              :placeholder="t('profile.newPasswordPlaceholder')"
              required
              :minlength="10"
              autocomplete="new-password"
            />
            <TextInput
              v-model="passwordForm.password_confirmation"
              type="password"
              :label="t('employees.list.confirmPassword')"
              :placeholder="t('employees.list.repeatPassword')"
              required
              :minlength="10"
              autocomplete="new-password"
              :error="passwordForm.password_confirmation !== '' && passwordForm.password !== passwordForm.password_confirmation
                ? t('profile.twoPasswordsMismatch')
                : null"
            />

            <div class="flex items-center gap-2 border-t border-hairline pt-3.5">
              <Button type="submit" :disabled="!passwordReady" :loading="passwordSaving">
                {{ passwordSaving ? t('employees.list.changing') : t('employees.list.changePassword') }}
              </Button>
            </div>
          </form>
        </Card>
      </div>
    </div>
  </AppShell>
</template>
