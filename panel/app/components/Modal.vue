<script setup lang="ts">
const open = defineModel<boolean>({ default: false })
defineProps<{ title?: string }>()
const { t } = useI18n()
const titleId = useId()
let previousBodyOverflow = ''

function close() {
  open.value = false
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') close()
}

watch(open, (value) => {
  if (value) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', onKeydown)
  } else {
    document.body.style.overflow = previousBodyOverflow
    document.removeEventListener('keydown', onKeydown)
  }
})

onUnmounted(() => {
  document.body.style.overflow = previousBodyOverflow
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="transition duration-base ease-soft" enter-from-class="opacity-0"
      leave-active-class="transition duration-fast ease-soft" leave-to-class="opacity-0">
      <div v-if="open" class="safe-modal-frame fixed inset-0 z-50 flex items-center justify-center">
        <button type="button" class="absolute inset-0 bg-black/75" :aria-label="t('common.close')" @click="close" />
        <Transition appear enter-active-class="transition-opacity duration-base" enter-from-class="opacity-0">
          <section class="elevated-overlay relative z-10 flex max-h-full w-full max-w-md flex-col overflow-hidden bg-surface" role="dialog" aria-modal="true" :aria-labelledby="title ? titleId : undefined">
            <header v-if="title" class="flex flex-none items-center justify-between border-b border-hairline px-4 py-3.5 sm:px-5">
              <h2 :id="titleId" class="text-[14px] font-semibold text-ink">{{ title }}</h2>
              <button type="button" @click="close" class="grid h-8 w-8 place-items-center rounded-sm text-ink-faint transition-colors hover:bg-surface-sunken hover:text-ink" :aria-label="t('common.close')">
                <Icon name="close" class="h-4 w-4" />
              </button>
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto p-4 text-[13px] text-ink sm:p-5">
              <slot />
            </div>
            <footer v-if="$slots.footer" class="modal-actions flex flex-none flex-col gap-2 border-t border-hairline p-4 sm:flex-row sm:justify-end sm:px-5">
              <slot name="footer" />
            </footer>
          </section>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
