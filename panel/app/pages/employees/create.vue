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

const error = ref('')
const submitting = ref(false)

async function submit() {
  error.value = ''
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
    await navigateTo('/employees')
  } catch {
    error.value = 'Save failed — check the fields (username must be unique, password at least 8 characters).'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-8">
    <h1 class="mb-4 text-xl font-semibold text-slate-900">Add employee</h1>

    <p v-if="error" class="mb-4 text-sm text-red-600">{{ error }}</p>

    <form @submit.prevent="submit" class="max-w-md space-y-3 rounded border border-slate-200 bg-white p-4">
      <div>
        <label class="block text-xs text-slate-500">Name</label>
        <input v-model="form.name" required class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
      </div>
      <div>
        <label class="block text-xs text-slate-500">Phone</label>
        <input v-model="form.phone" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
      </div>
      <div>
        <label class="block text-xs text-slate-500">Username</label>
        <input v-model="form.username" required class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
        <p class="mt-1 text-xs text-slate-400">Used to log in on the mobile app — not an email.</p>
      </div>
      <div>
        <label class="block text-xs text-slate-500">Password</label>
        <input v-model="form.password" type="password" required minlength="8" class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
      </div>
      <div>
        <label class="text-sm">
          <input type="checkbox" v-model="form.is_active" class="mr-1" />Active
        </label>
      </div>
      <div class="pt-2">
        <button type="submit" :disabled="submitting" class="rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50">
          Create employee
        </button>
        <NuxtLink to="/employees" class="ml-2 rounded border border-slate-300 px-3 py-1.5 text-sm">Cancel</NuxtLink>
      </div>
    </form>
  </div>
</template>
