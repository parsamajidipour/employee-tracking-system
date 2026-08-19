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
    <div class="relative hidden flex-1 flex-col justify-between overflow-hidden bg-surface-dark p-10 text-ink-dark lg:flex">
      <div class="flex items-center gap-2.5">
        <span class="grid h-8 w-8 place-items-center rounded-sm bg-primary text-white">
          <Icon name="map-pin" class="h-4 w-4" />
        </span>
        <span class="text-[14px] font-semibold tracking-tight">Smart Inspection</span>
      </div>

      <div class="enter max-w-md">
        <h1 class="text-[28px] font-bold leading-tight tracking-tight text-ink-dark">
          Know where your team is, only when it matters.
        </h1>
        <p class="mt-3 text-[13.5px] leading-relaxed text-ink-dark-soft">
          Live location during working hours, nothing outside it. Full shift history,
          route playback and audit trails for every trail viewed.
        </p>
      </div>

      <p class="text-[11.5px] text-ink-dark-soft">Operations panel · v1</p>

      <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-primary/10 blur-3xl"></div>
      <div class="pointer-events-none absolute -bottom-32 -left-16 h-72 w-72 rounded-full bg-primary/10 blur-3xl"></div>
    </div>

    <div class="flex flex-1 items-center justify-center p-6">
      <div class="enter w-full max-w-sm">
        <div class="mb-8 flex flex-col items-center text-center lg:hidden">
          <img src="/logo.png" alt="" class="h-12 w-12" />
          <h1 class="mt-3">Smart Inspection</h1>
        </div>

        <div class="mb-6">
          <h2 class="text-[19px] font-bold tracking-tight text-ink">Sign in</h2>
          <p class="muted mt-1 text-[13px]">Use your work email address.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-3.5">
          <InlineAlert v-if="error">{{ error }}</InlineAlert>

          <TextInput v-model="email" type="email" label="Email" icon="mail" autocomplete="username" required />
          <TextInput v-model="password" type="password" label="Password" icon="lock" autocomplete="current-password" required />

          <Button type="submit" :disabled="submitting" class="w-full justify-center">
            {{ submitting ? 'Signing in…' : 'Sign in' }}
          </Button>
        </form>

        <p class="muted mt-6 text-center text-[11.5px]">
          Location data is only visible during an employee's working hours.
        </p>
      </div>
    </div>
  </div>
</template>
