<script setup lang="ts">
const route = useRoute()
const { t, locale } = useI18n()
const { user, refresh } = useAuthUser()
const { isOpen, close } = useSidebar()
const railExpanded = useState('rail-expanded', () => true)

const links = computed(() => [
  { to: '/map', label: t('nav.liveMap'), icon: 'map-pin' },
  { to: '/shift-templates', label: t('nav.shiftTemplates'), icon: 'calendar' },
  { to: '/employees', label: t('nav.employees'), icon: 'users' },
])

const caseLinks = computed(() => [
  { to: '/cases', label: t('nav.cases'), icon: 'briefcase' },
])

const adminLinks = computed(() => [{ to: '/app-releases', label: t('nav.appReleases'), icon: 'download' }])

const trailingLinks = computed(() => [{ to: '/profile', label: t('nav.adminProfile'), icon: 'user-circle' }])

const visibleLinks = computed(() => [
  ...links.value,
  ...(user.value?.role === 'admin' || user.value?.role === 'supervisor' ? caseLinks.value : []),
  ...(user.value?.role === 'admin' ? adminLinks.value : []),
  ...trailingLinks.value,
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
    class="fixed inset-y-0 start-0 z-50 flex w-full flex-none flex-col bg-surface-dark text-ink-dark
           transition-transform duration-base ease-soft
           sm:w-80 lg:static lg:z-auto lg:!translate-x-0"
    :class="[
      isOpen ? '!translate-x-0 shadow-dark-key' : locale === 'ar' ? 'translate-x-full' : '-translate-x-full',
      railExpanded ? 'lg:w-72' : 'lg:w-[84px]',
    ]"
    style="transition-property: transform, width"
  >
    <div class="app-sidebar-header flex flex-none items-center gap-3">
      <span class="grid h-9 w-9 flex-none place-items-center rounded-sm bg-primary text-white">
        <Icon name="map-pin" class="h-5 w-5" />
      </span>
      <span v-if="railExpanded" class="truncate text-[15px] font-semibold tracking-tight">{{ t('app.name') }}</span>
      <button
        type="button"
        class="ms-auto grid h-9 w-9 flex-none place-items-center rounded-sm text-ink-dark-soft transition-colors hover:bg-surface-dark-hover hover:text-ink-dark lg:hidden"
        :aria-label="t('nav.closeMenu')"
        @click="close"
      >
        <Icon name="close" class="h-5 w-5" />
      </button>
    </div>

    <nav class="app-sidebar-nav flex-1 space-y-1.5 overflow-y-auto py-3">
      <NuxtLink
        v-for="link in visibleLinks"
        :key="link.to"
        :to="link.to"
        :title="railExpanded ? undefined : link.label"
        class="flex h-12 items-center gap-3.5 rounded-sm px-3.5 text-[14.5px] font-medium transition-colors duration-fast ease-soft"
        :class="
          route.path.startsWith(link.to)
            ? 'bg-primary/15 text-white'
            : 'text-ink-dark-soft hover:bg-surface-dark-hover hover:text-ink-dark'
        "
      >
        <Icon :name="link.icon" class="h-[22px] w-[22px] flex-none" />
        <span v-if="railExpanded" class="truncate">{{ link.label }}</span>
      </NuxtLink>
    </nav>

    <div class="app-sidebar-footer flex-none space-y-1.5 border-t border-hairline-dark pt-3.5">
      <button
        type="button"
        class="hidden h-11 w-full items-center gap-3.5 rounded-sm px-3.5 text-[13.5px] font-medium text-ink-dark-soft transition-colors hover:bg-surface-dark-hover hover:text-ink-dark lg:flex"
        @click="railExpanded = !railExpanded"
      >
        <Icon name="forward" class="directional-icon h-[22px] w-[22px] flex-none transition-transform duration-base" :class="railExpanded ? 'rotate-180' : ''" />
        <span v-if="railExpanded" class="truncate">{{ t('nav.collapse') }}</span>
      </button>

      <div class="flex items-center gap-3 px-3.5 py-2">
        <span class="grid h-9 w-9 flex-none place-items-center rounded-full bg-surface-dark-hover text-[13px] font-bold text-ink-dark">
          {{ (user?.name ?? '?').charAt(0).toUpperCase() }}
        </span>
        <div v-if="railExpanded" class="min-w-0">
          <p class="truncate text-[13.5px] font-semibold text-ink-dark">{{ user?.name ?? t('nav.signedIn') }}</p>
          <button type="button" class="min-h-10 text-[12px] font-medium text-ink-dark-soft transition-colors hover:text-state-danger" @click="signOut">
            {{ t('nav.signOut') }}
          </button>
        </div>
      </div>
    </div>
  </aside>
</template>
