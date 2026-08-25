<script setup lang="ts">
const props = withDefaults(
  defineProps<{ width?: number; align?: 'start' | 'end'; label?: string }>(),
  { width: 272, align: 'end', label: 'Open menu' },
)

const open = ref(false)
const triggerRef = ref<HTMLElement | null>(null)
const panelRef = ref<HTMLElement | null>(null)
const position = ref({ top: 0, left: 0, maxHeight: 320 })

const MARGIN = 8

function updatePosition() {
  const trigger = triggerRef.value
  if (!trigger) return

  const rect = trigger.getBoundingClientRect()
  const width = Math.min(props.width, window.innerWidth - MARGIN * 2)

  let left = props.align === 'end' ? rect.right - width : rect.left
  left = Math.max(MARGIN, Math.min(left, window.innerWidth - width - MARGIN))

  const below = window.innerHeight - rect.bottom - MARGIN * 2
  const above = rect.top - MARGIN * 2
  const placeAbove = below < 200 && above > below

  position.value = {
    top: placeAbove ? MARGIN : rect.bottom + 6,
    left,
    maxHeight: placeAbove ? above : below,
  }
}

function close() {
  open.value = false
}

function toggle() {
  if (open.value) {
    close()
    return
  }
  open.value = true
  nextTick(updatePosition)
}

function onDocClick(e: MouseEvent) {
  const target = e.target as Node
  if (panelRef.value?.contains(target) || triggerRef.value?.contains(target)) return
  close()
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') close()
}

function onReposition() {
  if (open.value) updatePosition()
}

watch(open, (value) => {
  if (value) {
    document.addEventListener('click', onDocClick, true)
    document.addEventListener('keydown', onKeydown)
    window.addEventListener('resize', onReposition)
    window.addEventListener('scroll', onReposition, true)
  } else {
    document.removeEventListener('click', onDocClick, true)
    document.removeEventListener('keydown', onKeydown)
    window.removeEventListener('resize', onReposition)
    window.removeEventListener('scroll', onReposition, true)
  }
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick, true)
  document.removeEventListener('keydown', onKeydown)
  window.removeEventListener('resize', onReposition)
  window.removeEventListener('scroll', onReposition, true)
})

defineExpose({ close })
</script>

<template>
  <span class="inline-flex">
    <span ref="triggerRef" class="inline-flex">
      <slot name="trigger" :open="open" :toggle="toggle">
        <button
          type="button"
          class="grid h-9 w-9 place-items-center rounded-sm text-ink-soft transition-colors duration-fast ease-soft hover:bg-surface-sunken hover:text-ink"
          :class="open ? 'bg-surface-sunken text-ink' : ''"
          :aria-expanded="open"
          :aria-label="label"
          @click.stop="toggle"
        >
          <Icon name="more-horizontal" class="h-4 w-4" />
        </button>
      </slot>
    </span>

    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-fast ease-soft"
        enter-from-class="opacity-0 scale-95"
        leave-active-class="transition duration-fast ease-soft"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="open"
          ref="panelRef"
          class="surface fixed z-50 overflow-y-auto p-1.5"
          :style="{
            top: `${position.top}px`,
            left: `${position.left}px`,
            width: `min(${width}px, calc(100vw - ${MARGIN * 2}px))`,
            maxHeight: `${position.maxHeight}px`,
          }"
        >
          <slot :close="close" />
        </div>
      </Transition>
    </Teleport>
  </span>
</template>
