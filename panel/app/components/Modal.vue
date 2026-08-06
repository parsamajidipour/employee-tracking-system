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
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @click.self="close">
      <div class="w-full max-w-md rounded-lg bg-white p-5 shadow-lg" role="dialog" aria-modal="true">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-900">{{ title }}</h2>
          <button type="button" @click="close" class="text-slate-400 hover:text-slate-600" aria-label="Close">✕</button>
        </div>
        <div class="text-sm text-slate-700">
          <slot />
        </div>
        <div v-if="$slots.footer" class="mt-4 flex justify-end gap-2">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>
