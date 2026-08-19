<script setup lang="ts">
const route = useRoute()
const { user, refresh } = useAuthUser()
const { isOpen, close } = useSidebar()
const railExpanded = useState('rail-expanded', () => true)

const links = [
  { to: '/map', label: 'Live map', icon: 'map-pin' },
  { to: '/shift-templates', label: 'Shift templates', icon: 'calendar' },
  { to: '/employees', label: 'Employees', icon: 'users' },
]

const adminLinks = [{ to: '/app-releases', label: 'App releases', icon: 'download' }]

const trailingLinks = [{ to: '/profile', label: 'Admin profile', icon: 'user-circle' }]

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
      class="fixed inset-0 z-40 bg-ink/50 transition-opacity duration-base ease-soft lg:hidden"
      @click="close"
    />
  </Teleport>

  <aside
    class="fixed inset-y-0 left-0 z-50 flex w-full flex-none -translate-x-full flex-col bg-surface-dark text-ink-dark
           transition-transform duration-base ease-soft
           sm:w-80 lg:static lg:z-auto lg:translate-x-0"
    :class="[isOpen ? 'translate-x-0 shadow-dark-key' : '', railExpanded ? 'lg:w-64' : 'lg:w-[76px]']"
    style="transition-property: transform, width"
  >
    <div class="flex h-16 flex-none items-center gap-2.5 px-4">
      <span class="grid h-8 w-8 flex-none place-items-center rounded-sm bg-primary text-white">
        <Icon name="map-pin" class="h-[18px] w-[18px]" />
      </span>
      <span v-if="railExpanded" class="truncate text-[14.5px] font-semibold tracking-tight">Smart Inspection</span>
      <button
        type="button"
        class="ml-auto grid h-8 w-8 flex-none place-items-center rounded-sm text-ink-dark-soft transition-colors hover:bg-surface-dark-hover hover:text-ink-dark lg:hidden"
        aria-label="Close menu"
        @click="close"
      >
        <Icon name="close" class="h-[18px] w-[18px]" />
      </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2.5">
      <NuxtLink
        v-for="link in visibleLinks"
        :key="link.to"
        :to="link.to"
        :title="railExpanded ? undefined : link.label"
        class="flex h-11 items-center gap-3 rounded-sm px-3 text-[13.5px] font-medium transition-colors duration-fast ease-soft"
        :class="
          route.path.startsWith(link.to)
            ? 'bg-primary/15 text-white'
            : 'text-ink-dark-soft hover:bg-surface-dark-hover hover:text-ink-dark'
        "
      >
        <Icon :name="link.icon" class="h-5 w-5 flex-none" />
        <span v-if="railExpanded" class="truncate">{{ link.label }}</span>
      </NuxtLink>
    </nav>

    <div class="flex-none space-y-1 border-t border-hairline-dark px-3 py-3">
      <button
        type="button"
        class="hidden h-10 w-full items-center gap-3 rounded-sm px-3 text-[13px] font-medium text-ink-dark-soft transition-colors hover:bg-surface-dark-hover hover:text-ink-dark lg:flex"
        @click="railExpanded = !railExpanded"
      >
        <Icon name="forward" class="h-5 w-5 flex-none transition-transform duration-base" :class="railExpanded ? 'rotate-180' : ''" />
        <span v-if="railExpanded" class="truncate">Collapse</span>
      </button>

      <div class="flex items-center gap-2.5 px-3 py-1.5">
        <span class="grid h-8 w-8 flex-none place-items-center rounded-full bg-surface-dark-hover text-[12px] font-bold text-ink-dark">
          {{ (user?.name ?? '?').charAt(0).toUpperCase() }}
        </span>
        <div v-if="railExpanded" class="min-w-0">
          <p class="truncate text-[13px] font-semibold text-ink-dark">{{ user?.name ?? 'Signed in' }}</p>
          <button type="button" class="text-[11.5px] font-medium text-ink-dark-soft transition-colors hover:text-state-danger" @click="signOut">
            Sign out
          </button>
        </div>
      </div>
    </div>
  </aside>
</template>
