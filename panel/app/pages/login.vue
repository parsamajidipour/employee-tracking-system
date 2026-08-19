<script setup lang="ts">
definePageMeta({ layout: false })

const email = ref('')
const password = ref('')
const error = ref('')
const submitting = ref(false)
const router = useRouter()

async function submit() {
  error.value = ''
  submitting.value = true
  try {
    await ensureCsrfCookie()
    await apiFetch('/api/login', {
      method: 'POST',
      body: { email: email.value, password: password.value },
    })
    await router.push('/')
  } catch {
    error.value = 'Login failed — check the credentials and try again.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-dvh bg-canvas">
    <div class="relative hidden flex-1 flex-col justify-between overflow-hidden bg-surface-dark p-8 text-ink-dark lg:flex">
      <div class="bg-grid-dark pointer-events-none absolute inset-0"></div>

      <div class="relative flex items-center gap-2.5">
        <span class="grid h-9 w-9 place-items-center rounded-sm bg-primary text-white">
          <Icon name="map-pin" class="h-5 w-5" />
        </span>
        <span class="text-[14px] font-semibold tracking-tight">Smart Inspection</span>
      </div>

      <div class="enter relative max-w-sm">
        <h1 class="text-[22px] font-bold leading-snug tracking-tight text-ink-dark">
          Know where your team is, only when it matters.
        </h1>
        <p class="mt-2.5 text-[13px] leading-relaxed text-ink-dark-soft">
          Live location during working hours, nothing outside it. Full shift history,
          route playback and audit trails for every trail viewed.
        </p>
      </div>

      <p class="relative text-[11.5px] text-ink-dark-soft">Operations panel · v1</p>
    </div>

    <div class="flex flex-1 items-center justify-center p-5">
      <div class="enter w-full max-w-[368px]">
        <div class="mb-6 flex flex-col items-center gap-2 text-center lg:hidden">
          <span class="grid h-9 w-9 place-items-center rounded-sm bg-primary text-white">
            <Icon name="map-pin" class="h-5 w-5" />
          </span>
          <h1 class="mt-1">Smart Inspection</h1>
        </div>

        <div class="surface-flat px-6 py-7">
          <div class="mb-5">
            <h2 class="text-ink">Sign in</h2>
            <p class="muted mt-1 text-[12.5px]">Use your work email address.</p>
          </div>

          <form @submit.prevent="submit" class="space-y-3">
            <InlineAlert v-if="error">{{ error }}</InlineAlert>

            <TextInput v-model="email" type="email" label="Email" icon="mail" autocomplete="username" required />
            <TextInput v-model="password" type="password" label="Password" icon="lock" autocomplete="current-password" required />

            <Button type="submit" :disabled="submitting" class="w-full justify-center">
              {{ submitting ? 'Signing in…' : 'Sign in' }}
            </Button>
          </form>
        </div>

        <p class="muted mt-5 text-center text-[11.5px]">
          Location data is only visible during an employee's working hours.
        </p>
      </div>
    </div>
  </div>
</template>
