<script setup lang="ts">
const { user, refresh } = useAuthUser()
const toast = useToast()
const saving = ref(false)
const error = ref<string | null>(null)
const form = reactive({
  name: '',
  email: '',
  current_password: '',
  password: '',
  password_confirmation: '',
})

watchEffect(() => {
  if (!user.value) return
  form.name = user.value.name
  form.email = user.value.email
})

async function submit() {
  saving.value = true
  error.value = null
  try {
    await apiFetch('/api/v1/admin/profile', {
      method: 'PUT',
      body: {
        name: form.name,
        email: form.email,
        current_password: form.current_password || null,
        password: form.password || null,
        password_confirmation: form.password_confirmation || null,
      },
    })
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
    await refresh()
    toast.success('Profile updated.')
  } catch {
    error.value = 'Update failed. Check the current password, email and password requirements.'
  } finally {
    saving.value = false
  }
}

onMounted(refresh)
</script>

<template>
  <AppShell title="Admin profile">
    <form class="max-w-lg space-y-4 rounded-card border border-hairline bg-surface p-6" @submit.prevent="submit">
      <InlineAlert v-if="error">{{ error }}</InlineAlert>
      <TextInput v-model="form.name" label="Name" required autocomplete="name" />
      <TextInput v-model="form.email" type="email" label="Email" required autocomplete="email" />

      <div class="border-t border-hairline pt-4">
        <h2 class="mb-1 text-sm font-semibold text-ink">Change password</h2>
        <p class="mb-4 text-xs text-ink-faint">Leave these fields empty to keep the current password.</p>
        <div class="space-y-3">
          <TextInput v-model="form.current_password" type="password" label="Current password" autocomplete="current-password" />
          <TextInput v-model="form.password" type="password" label="New password" :minlength="10" autocomplete="new-password" />
          <TextInput v-model="form.password_confirmation" type="password" label="Confirm new password" :minlength="10" autocomplete="new-password" />
        </div>
      </div>

      <Button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save profile' }}</Button>
    </form>
  </AppShell>
</template>
