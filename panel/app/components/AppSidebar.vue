<script setup lang="ts">
const route = useRoute()
const { user, refresh } = useAuthUser()
const { isOpen, close } = useSidebar()
const { mode, setMode } = useTheme()
const systemPrefersDark = ref(false)
const isDark = computed(() => mode.value === 'dark' || (mode.value === 'system' && systemPrefersDark.value))

function toggleTheme() {
  setMode(isDark.value ? 'light' : 'dark')
}

onMounted(() => {
  const query = window.matchMedia('(prefers-color-scheme: dark)')
  systemPrefersDark.value = query.matches
  query.addEventListener('change', (event) => {
    systemPrefersDark.value = event.matches
  })
})

const links = [
  { to: '/map', label: 'Live map', icon: 'map-pin' },
  { to: '/shift-templates', label: 'Shift templates', icon: 'calendar' },
  { to: '/employees', label: 'Employees', icon: 'users' },
]

const adminLinks = [
  { to: '/app-releases', label: 'App releases', icon: 'download' },
]

const trailingLinks = [
  { to: '/profile', label: 'Admin profile', icon: 'user-circle' },
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
    class="fixed inset-y-0 left-0 z-50 flex w-full flex-none -translate-x-full flex-col border-r
           border-hairline bg-surface transition-transform duration-[220ms] ease-soft
           sm:w-80 lg:static lg:z-auto lg:w-64 lg:translate-x-0"
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
        <Icon name="close" class="h-5 w-5" />
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
        <Icon :name="link.icon" class="h-5 w-5 flex-none" />
        {{ link.label }}
      </NuxtLink>
    </nav>

    <div class="flex-none border-t border-hairline px-5 py-4">
      <div class="flex items-center justify-between gap-2">
        <div class="min-w-0">
          <p class="truncate text-sm font-semibold">{{ user?.name ?? 'Signed in' }}</p>
          <button
            type="button"
            class="mt-0.5 text-xs font-medium text-ink-soft transition-colors duration-150 hover:text-state-danger"
            @click="signOut"
          >
            Sign out
          </button>
        </div>
        <button
          type="button"
          class="grid h-9 w-9 flex-none place-items-center rounded-small text-ink-soft transition-colors hover:bg-surface-muted hover:text-ink"
          :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
          @click="toggleTheme"
        >
          <Icon :name="isDark ? 'sun' : 'moon'" class="h-5 w-5" />
        </button>
      </div>
    </div>
  </aside>
</template>
