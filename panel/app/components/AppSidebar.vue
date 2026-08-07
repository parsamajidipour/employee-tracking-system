<script setup lang="ts">
const route = useRoute()
const { user, refresh } = useAuthUser()

const links = [
  { to: '/map', label: 'Map' },
  { to: '/shift-templates', label: 'Shift templates' },
  { to: '/employees', label: 'Employees' },
]

// The auth middleware already fetches /api/user for any page reachable
// with a session; this only covers it if that hasn't happened yet.
onMounted(() => {
  if (!user.value) refresh()
})

async function signOut() {
  try {
    await apiFetch('/api/logout', { method: 'POST' })
  } catch {
    // Proceed to /login regardless — an already-expired session shouldn't
    // trap the admin on a sign-out button that appears to do nothing.
  }
  await navigateTo('/login')
}
</script>

<template>
  <aside class="flex w-56 flex-none flex-col border-r border-slate-200 bg-slate-50">
    <div class="flex h-14 flex-none items-center px-4">
      <span class="text-sm font-semibold text-slate-900">Inspection</span>
    </div>

    <nav class="flex-1 space-y-0.5 px-2">
      <NuxtLink
        v-for="link in links"
        :key="link.to"
        :to="link.to"
        class="block rounded px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900"
        :class="{ 'bg-blue-50 text-blue-700 hover:bg-blue-50 hover:text-blue-700': route.path.startsWith(link.to) }"
      >
        {{ link.label }}
      </NuxtLink>
    </nav>

    <div class="flex-none border-t border-slate-200 p-3">
      <p v-if="user" class="truncate text-xs font-medium text-slate-700">{{ user.name }}</p>
      <button type="button" @click="signOut" class="mt-1 text-xs text-slate-500 hover:text-slate-900 hover:underline">
        Sign out
      </button>
    </div>
  </aside>
</template>
