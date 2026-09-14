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

onMounted(() => {
  mediaQuery = window.matchMedia('(max-width: 639px)')
  syncViewport()
  mediaQuery.addEventListener('change', syncViewport)
})

onUnmounted(() => {
  mediaQuery?.removeEventListener('change', syncViewport)
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

    <Modal v-if="isMobile" v-model="open" :title="title">
      <div class="space-y-4">
        <slot />
      </div>
      <template #footer>
        <div class="flex w-full flex-col gap-2 sm:flex-row sm:justify-end">
          <slot name="footer" />
          <Button type="button" class="w-full justify-center sm:w-auto" @click="close">
            {{ t('common.done') }}
          </Button>
        </div>
      </template>
    </Modal>

    <div v-else class="hidden flex-wrap items-end gap-3.5 sm:flex">
      <slot />
      <div v-if="$slots.footer" class="contents">
        <slot name="footer" />
      </div>
    </div>
  </div>
</template>
