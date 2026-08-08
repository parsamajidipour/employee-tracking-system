<script setup lang="ts">
const route = useRoute()
const { user, refresh } = useAuthUser()

const links = [
  { to: '/map', label: 'Live map', icon: 'M12 21s-7-5.686-7-11a7 7 0 1 1 14 0c0 5.314-7 11-7 11Z M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z' },
  { to: '/shift-templates', label: 'Shift templates', icon: 'M8 2v3M16 2v3M3.5 9h17M4.5 5.5h15a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1Z' },
  { to: '/employees', label: 'Employees', icon: 'M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20M9.5 10.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM21 20v-1.5a4 4 0 0 0-3-3.87M16.5 3.87a4 4 0 0 1 0 7.75' },
  { to: '/profile', label: 'Admin profile', icon: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0' },
]

onMounted(() => {
  if (!user.value) refresh()
})

async function signOut() {
  try {
    await apiFetch('/api/logout', { method: 'POST' })
  } catch {
  }
  await navigateTo('/login')
}
</script>

<template>
  <aside class="flex w-60 flex-none flex-col border-r border-hairline bg-surface">
    <div class="flex h-16 flex-none items-center gap-2.5 px-5">
      <img src="/logo.png" alt="" class="h-7 w-7" />
      <span class="font-semibold">Smart Inspection</span>
    </div>

    <nav class="flex-1 space-y-1 px-3 py-2">
      <NuxtLink
        v-for="link in links"
        :key="link.to"
        :to="link.to"
        class="flex items-center gap-3 rounded-control px-3 py-2.5 text-sm font-medium transition-colors duration-150 ease-soft"
        :class="
          route.path.startsWith(link.to)
            ? 'bg-primary-soft text-primary-strong'
            : 'text-ink-soft hover:bg-surface-muted hover:text-ink'
        "
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
          stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 flex-none">
          <path :d="link.icon" />
        </svg>
        {{ link.label }}
      </NuxtLink>
    </nav>

    <div class="flex-none border-t border-hairline px-5 py-4">
      <p class="truncate text-sm font-medium">{{ user?.name ?? 'Signed in' }}</p>
      <button
        type="button"
        class="mt-0.5 text-xs text-ink-soft transition-colors duration-150 hover:text-state-danger"
        @click="signOut"
      >
        Sign out
      </button>
    </div>
  </aside>
</template>
