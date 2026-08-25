<script setup lang="ts">
const open = defineModel<boolean>({ default: false })
defineProps<{ title?: string }>()

function close() {
  open.value = false
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') close()
}

watch(open, (value) => {
  if (value) document.addEventListener('keydown', onKeydown)
  else document.removeEventListener('keydown', onKeydown)
})

onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="transition duration-base ease-soft" enter-from-class="opacity-0"
      leave-active-class="transition duration-fast ease-soft" leave-to-class="opacity-0">
      <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-3 sm:p-4" @click.self="close">
        <Transition appear enter-active-class="scale-in">
          <div class="surface max-h-[calc(100dvh-24px)] w-full max-w-md overflow-y-auto p-4 sm:max-h-[calc(100dvh-32px)] sm:p-5" role="dialog" aria-modal="true">
            <div class="mb-3.5 flex items-center justify-between">
              <h2 class="text-[14px] font-semibold text-ink">{{ title }}</h2>
              <button type="button" @click="close" class="grid h-7 w-7 place-items-center rounded-sm text-ink-faint transition-colors hover:bg-surface-sunken hover:text-ink" aria-label="Close">
                <Icon name="close" class="h-4 w-4" />
              </button>
            </div>
            <div class="text-[13px] text-ink">
              <slot />
            </div>
            <div v-if="$slots.footer" class="mt-4 flex flex-wrap justify-end gap-2">
              <slot name="footer" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
