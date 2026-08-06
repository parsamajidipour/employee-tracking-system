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
  <div class="flex min-h-screen items-center justify-center bg-slate-50">
    <form @submit.prevent="submit" class="w-full max-w-sm space-y-4 rounded-lg border border-slate-200 bg-white p-6">
      <h1 class="text-base font-semibold text-slate-900">Sign in</h1>

      <InlineAlert v-if="error">{{ error }}</InlineAlert>

      <TextInput v-model="email" type="email" label="Email" required />
      <TextInput v-model="password" type="password" label="Password" required />

      <Button type="submit" :disabled="submitting" class="w-full justify-center">
        {{ submitting ? 'Signing in…' : 'Sign in' }}
      </Button>
    </form>
  </div>
</template>
