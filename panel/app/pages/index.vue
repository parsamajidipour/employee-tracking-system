<script setup>
const user = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    user.value = await apiFetch('/api/user')
  } catch {
    user.value = null
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-8">
    <div v-if="loading" class="text-slate-500">Loading…</div>
    <div v-else-if="user" class="space-y-2">
      <h1 class="text-xl font-semibold text-slate-900">
        Signed in as {{ user.name }} ({{ user.email }})
      </h1>
      <p class="text-sm text-slate-500">
        This came from an authenticated request to the api/ origin.
      </p>
    </div>
    <div v-else class="space-y-2">
      <p class="text-slate-700">Not signed in.</p>
      <NuxtLink to="/login" class="text-blue-600 underline">Go to login</NuxtLink>
    </div>
  </div>
</template>
