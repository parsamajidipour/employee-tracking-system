<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  activeCount?: number
}>(), {
  activeCount: 0,
})

const open = ref(false)
const isMobile = ref(false)
const { t } = useI18n()
let mediaQuery: MediaQueryList | undefined

function syncViewport(event?: MediaQueryListEvent) {
  isMobile.value = event?.matches ?? mediaQuery?.matches ?? false
  if (!isMobile.value) open.value = false
}

function close() {
  open.value = false
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') close()
}

watch([open, isMobile], ([isOpen, mobile]) => {
  if (!import.meta.client) return
  document.body.style.overflow = isOpen && mobile ? 'hidden' : ''
})

onMounted(() => {
  mediaQuery = window.matchMedia('(max-width: 639px)')
  syncViewport()
  mediaQuery.addEventListener('change', syncViewport)
  document.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  mediaQuery?.removeEventListener('change', syncViewport)
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="contents">
    <Button
      variant="secondary"
      type="button"
      class="w-full justify-between sm:hidden"
      :aria-expanded="open"
      @click="open = true"
    >
      <span class="flex items-center gap-2">
        <Icon name="filter" class="h-4 w-4" />
        {{ title }}
      </span>
      <span v-if="activeCount > 0" class="grid h-5 min-w-5 place-items-center rounded-pill bg-primary px-1.5 text-[11px] font-bold text-white">
        {{ activeCount }}
      </span>
      <Icon v-else name="chevron-right" class="directional-icon h-4 w-4 text-ink-faint" />
    </Button>

    <Teleport to="body" :disabled="!isMobile">
      <div
        v-if="!isMobile || open"
        :class="isMobile ? 'safe-modal-frame fixed inset-0 z-50 flex items-center justify-center' : 'contents'"
      >
        <button
          v-if="isMobile"
          type="button"
          class="absolute inset-0 bg-black/75"
          :aria-label="t('common.close')"
          @click="close"
        />

        <section
          :class="isMobile
            ? 'surface relative z-10 flex max-h-full w-full max-w-md flex-col overflow-hidden border border-hairline'
            : 'hidden flex-wrap items-end gap-3.5 sm:flex'"
          :role="isMobile ? 'dialog' : undefined"
          :aria-modal="isMobile ? true : undefined"
          :aria-label="title"
        >
          <header v-if="isMobile" class="flex flex-none items-center justify-between border-b border-hairline px-4 py-3.5">
            <h2 class="text-[14px] font-semibold text-ink">{{ title }}</h2>
            <button
              type="button"
              class="grid h-8 w-8 place-items-center rounded-sm text-ink-faint hover:bg-surface-sunken hover:text-ink"
              :aria-label="t('common.close')"
              @click="close"
            >
              <Icon name="close" class="h-4 w-4" />
            </button>
          </header>

          <div :class="isMobile ? 'min-h-0 flex-1 space-y-4 overflow-y-auto p-4' : 'contents'">
            <slot />
          </div>

          <footer
            v-if="isMobile || $slots.footer"
            :class="isMobile ? 'flex flex-none flex-col gap-2 border-t border-hairline p-4' : 'contents'"
          >
            <slot name="footer" />
            <Button v-if="isMobile" type="button" class="w-full justify-center" @click="close">
              {{ t('common.done') }}
            </Button>
          </footer>
        </section>
      </div>
    </Teleport>
  </div>
</template>
