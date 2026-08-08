<script setup lang="ts">
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
  <div class="flex min-h-screen items-center justify-center bg-canvas px-5 py-10">
    <div class="enter w-full max-w-sm">
      <div class="mb-8 flex flex-col items-center text-center">
        <img src="/logo.png" alt="" class="h-16 w-16" />
        <h1 class="mt-3">Smart Inspection</h1>
        <p class="muted mt-1 text-sm">Operations panel</p>
      </div>

      <form @submit.prevent="submit" class="card space-y-4 p-6">
        <div>
          <h2>Sign in</h2>
          <p class="muted mt-1 text-sm">Use your work email address.</p>
        </div>

        <InlineAlert v-if="error">{{ error }}</InlineAlert>

        <TextInput v-model="email" type="email" label="Email" autocomplete="username" required />
        <TextInput v-model="password" type="password" label="Password" autocomplete="current-password" required />

        <Button type="submit" :disabled="submitting" class="w-full justify-center">
          {{ submitting ? 'Signing in…' : 'Sign in' }}
        </Button>
      </form>

      <p class="muted mt-6 text-center text-xs">
        Location data is only visible during an employee's working hours.
      </p>
    </div>
  </div>
</template>
