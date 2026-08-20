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
  <div class="flex min-h-dvh items-center justify-center bg-canvas p-5">
    <div class="enter w-full max-w-[400px]">
      <div class="mb-6 flex flex-col items-center gap-2.5 text-center">
        <span class="grid h-9 w-9 place-items-center rounded-sm bg-primary text-white">
          <Icon name="map-pin" class="h-5 w-5" />
        </span>
        <h1>Smart Inspection</h1>
      </div>

      <div class="surface-flat px-6 py-7">
        <div class="mb-5">
          <h2 class="text-ink">Sign in</h2>
          <p class="muted mt-1 text-[12.5px]">Use your work email address.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-3.5">
          <InlineAlert v-if="error">{{ error }}</InlineAlert>

          <TextInput v-model="email" type="email" label="Email" icon="mail" autocomplete="username" required />
          <TextInput v-model="password" type="password" label="Password" icon="lock" autocomplete="current-password" required />

          <Button type="submit" :loading="submitting" class="w-full justify-center">
            {{ submitting ? 'Signing in…' : 'Sign in' }}
          </Button>
        </form>
      </div>

      <p class="muted mt-5 text-center text-[11.5px]">
        Location data is only visible during an employee's working hours.
      </p>
    </div>
  </div>
</template>
