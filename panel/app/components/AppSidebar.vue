<script setup lang="ts">
const route = useRoute()
const { user, refresh } = useAuthUser()
const { isOpen, close } = useSidebar()

const links = [
  { to: '/map', label: 'Live map', icon: 'M12 21s-7-5.686-7-11a7 7 0 1 1 14 0c0 5.314-7 11-7 11Z M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z' },
  { to: '/shift-templates', label: 'Shift templates', icon: 'M8 2v3M16 2v3M3.5 9h17M4.5 5.5h15a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1Z' },
  { to: '/employees', label: 'Employees', icon: 'M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20M9.5 10.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM21 20v-1.5a4 4 0 0 0-3-3.87M16.5 3.87a4 4 0 0 1 0 7.75' },
]

const adminLinks = [
  { to: '/app-releases', label: 'App releases', icon: 'M12 3v12m0 0 4-4m-4 4-4-4M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2' },
]

const trailingLinks = [
  { to: '/profile', label: 'Admin profile', icon: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0' },
]

const visibleLinks = computed(() => [
  ...links,
  ...(user.value?.role === 'admin' ? adminLinks : []),
  ...trailingLinks,
])

onMounted(() => {
  if (!user.value) refresh()
})

watch(
  () => route.path,
  () => close(),
)

async function signOut() {
  try {
    await apiFetch('/api/logout', { method: 'POST' })
  } catch {
  }
  await navigateTo('/login')
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-40 bg-ink/40 transition-opacity duration-150 ease-soft lg:hidden"
      @click="close"
    />
  </Teleport>

  <aside
    class="fixed inset-y-0 left-0 z-50 flex w-72 flex-none -translate-x-full flex-col border-r
           border-hairline bg-surface transition-transform duration-[220ms] ease-soft
           lg:static lg:z-auto lg:w-64 lg:translate-x-0"
    :class="isOpen ? 'translate-x-0 shadow-raised' : ''"
  >
    <div class="flex h-16 flex-none items-center justify-between gap-2.5 px-5">
      <div class="flex items-center gap-2.5">
        <img src="/logo.png" alt="" class="h-8 w-8" />
        <span class="text-[15px] font-bold tracking-tight">Smart Inspection</span>
      </div>
      <button
        type="button"
        class="grid h-9 w-9 place-items-center rounded-small text-ink-soft transition-colors hover:bg-surface-muted hover:text-ink lg:hidden"
        aria-label="Close menu"
        @click="close"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" class="h-5 w-5">
          <path d="M6 6l12 12M18 6 6 18" />
        </svg>
      </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
      <NuxtLink
        v-for="link in visibleLinks"
        :key="link.to"
        :to="link.to"
        class="flex min-h-11 items-center gap-3 rounded-control px-3 py-2.5 text-sm font-semibold transition-colors duration-150 ease-soft"
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
      <p class="truncate text-sm font-semibold">{{ user?.name ?? 'Signed in' }}</p>
      <button
        type="button"
        class="mt-0.5 text-xs font-medium text-ink-soft transition-colors duration-150 hover:text-state-danger"
        @click="signOut"
      >
        Sign out
      </button>
    </div>
  </aside>
</template>
