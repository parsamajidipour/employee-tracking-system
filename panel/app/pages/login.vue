<script setup>
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
    <form @submit.prevent="submit" class="w-full max-w-sm space-y-4 rounded-lg bg-white p-6 shadow">
      <h1 class="text-lg font-semibold text-slate-900">Sign in</h1>
      <input
        v-model="email"
        type="email"
        placeholder="Email"
        required
        class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
      />
      <input
        v-model="password"
        type="password"
        placeholder="Password"
        required
        class="w-full rounded border border-slate-300 px-3 py-2 text-sm"
      />
      <button
        type="submit"
        :disabled="submitting"
        class="w-full rounded bg-slate-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
      >
        {{ submitting ? 'Signing in…' : 'Sign in' }}
      </button>
      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
    </form>
  </div>
</template>
