<script setup lang="ts">
const open = defineModel<boolean>({ default: false })
withDefaults(defineProps<{ title?: string; width?: string }>(), { width: 'max-w-md' })

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
      <div v-if="open" class="fixed inset-0 z-50 bg-ink/50" @click.self="close">
        <Transition
          enter-active-class="transition duration-base ease-soft"
          enter-from-class="translate-x-full"
          leave-active-class="transition duration-fast ease-soft"
          leave-to-class="translate-x-full"
        >
          <aside v-if="open" class="surface fixed inset-y-0 right-0 flex w-full flex-col rounded-l-lg rounded-r-none" :class="width">
            <div class="flex flex-none items-center justify-between border-b border-hairline px-5 py-4">
              <h2 class="text-[14px] font-semibold text-ink">{{ title }}</h2>
              <button type="button" @click="close" class="grid h-7 w-7 place-items-center rounded-sm text-ink-faint transition-colors hover:bg-surface-sunken hover:text-ink" aria-label="Close">
                <Icon name="close" class="h-4 w-4" />
              </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
              <slot />
            </div>
            <div v-if="$slots.footer" class="flex-none border-t border-hairline px-5 py-3.5">
              <slot name="footer" />
            </div>
          </aside>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
