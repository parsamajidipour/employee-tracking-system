<script setup lang="ts">
definePageMeta({ layout: false })

const email = ref('')
const password = ref('')
const error = ref('')
const submitting = ref(false)
const router = useRouter()
const { t } = useI18n()

async function submit() {
  error.value = ''
  submitting.value = true
  try {
    await ensureCsrfCookie()
    await apiFetch('/api/login', {
      method: 'POST',
      body: { email: email.value, password: password.value },
    })
    await router.replace('/map')
  } catch {
    error.value = t('auth.failed')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="relative flex min-h-dvh items-center justify-center bg-canvas p-5">
    <div class="absolute end-5 top-5 text-ink-soft">
      <LanguageSwitcher />
    </div>
    <div class="enter w-full max-w-[400px]">
      <div class="mb-6 flex flex-col items-center gap-2.5 text-center">
        <span class="grid h-9 w-9 place-items-center rounded-sm bg-primary text-white">
          <Icon name="map-pin" class="h-5 w-5" />
        </span>
        <h1>{{ t('app.name') }}</h1>
      </div>

      <div class="surface-flat px-6 py-7">
        <div class="mb-5">
          <h2 class="text-ink">{{ t('auth.signIn') }}</h2>
          <p class="muted mt-1 text-[12.5px]">{{ t('auth.workEmail') }}</p>
        </div>

        <form @submit.prevent="submit" class="space-y-3.5">
          <InlineAlert v-if="error">{{ error }}</InlineAlert>

          <TextInput v-model="email" type="email" :label="t('common.email')" :placeholder="t('auth.emailPlaceholder')" icon="mail" autocomplete="username" required />
          <TextInput v-model="password" type="password" :label="t('common.password')" :placeholder="t('auth.passwordPlaceholder')" icon="lock" autocomplete="current-password" required />

          <Button type="submit" :loading="submitting" class="w-full justify-center">
            {{ submitting ? t('auth.signingIn') : t('auth.signIn') }}
          </Button>
        </form>
      </div>
    </div>
  </div>
</template>
