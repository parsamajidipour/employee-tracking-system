<script setup lang="ts">
// Always creates role=employee — there is no role picker here. Admin/hr/
// supervisor accounts aren't created through the panel today (see
// api/app/Http/Requests/StoreEmployeeRequest.php's docblock).
const form = reactive({
  name: '',
  phone: '',
  username: '',
  password: '',
  is_active: true,
})

const error = ref<string | null>(null)
const submitting = ref(false)
const toast = useToast()

async function submit() {
  error.value = null
  submitting.value = true
  try {
    await apiFetch('/api/v1/employees', {
      method: 'POST',
      body: {
        name: form.name,
        phone: form.phone || null,
        username: form.username,
        password: form.password,
        is_active: form.is_active,
      },
    })
    toast.success('Employee created.')
    await navigateTo('/employees')
  } catch {
    error.value = 'Save failed — check the fields (username must be unique, password at least 8 characters).'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppShell title="Add employee">
    <form @submit.prevent="submit" class="max-w-md space-y-3 rounded border border-slate-200 bg-white p-4">
      <InlineAlert v-if="error">{{ error }}</InlineAlert>

      <TextInput v-model="form.name" label="Name" required />
      <TextInput v-model="form.phone" label="Phone" />
      <TextInput v-model="form.username" label="Username" required hint="Used to log in on the mobile app — not an email." />
      <TextInput v-model="form.password" type="password" label="Password" required :minlength="8" />

      <label class="flex items-center gap-2 text-sm text-slate-700">
        <input type="checkbox" v-model="form.is_active" class="accent-blue-600" />
        Active
      </label>

      <div class="flex items-center gap-2 pt-2">
        <Button type="submit" :disabled="submitting">Create employee</Button>
        <Button variant="secondary" to="/employees">Cancel</Button>
      </div>
    </form>
  </AppShell>
</template>
