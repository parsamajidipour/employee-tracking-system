<script setup lang="ts">
const props = withDefaults(
  defineProps<{ width?: number; align?: 'start' | 'end'; label?: string }>(),
  { width: 272, align: 'end' },
)
const { t, locale } = useI18n()

const open = ref(false)
const triggerRef = ref<HTMLElement | null>(null)
const panelRef = ref<HTMLElement | null>(null)
const position = ref({ top: 0, left: 0, maxHeight: 320 })

const MARGIN = 8

function updatePosition() {
  const trigger = triggerRef.value
  if (!trigger) return

  const viewport = window.visualViewport
  const viewportLeft = viewport?.offsetLeft ?? 0
  const viewportTop = viewport?.offsetTop ?? 0
  const viewportWidth = viewport?.width ?? window.innerWidth
  const viewportHeight = viewport?.height ?? window.innerHeight
  const rect = trigger.getBoundingClientRect()
  const width = Math.min(props.width, viewportWidth - MARGIN * 2)

  const endAligned = props.align === 'end'
  let left = locale.value === 'ar'
    ? (endAligned ? rect.left : rect.right - width)
    : (endAligned ? rect.right - width : rect.left)
  left = Math.max(viewportLeft + MARGIN, Math.min(left, viewportLeft + viewportWidth - width - MARGIN))

  const below = viewportTop + viewportHeight - rect.bottom - MARGIN * 2
  const above = rect.top - viewportTop - MARGIN * 2
  const placeAbove = below < 200 && above > below
  const panelHeight = Math.min(panelRef.value?.scrollHeight ?? 320, placeAbove ? above : below)

  position.value = {
    top: placeAbove ? Math.max(viewportTop + MARGIN, rect.top - panelHeight - 6) : rect.bottom + 6,
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
    window.visualViewport?.addEventListener('resize', onReposition)
    window.visualViewport?.addEventListener('scroll', onReposition)
  } else {
    document.removeEventListener('click', onDocClick, true)
    document.removeEventListener('keydown', onKeydown)
    window.removeEventListener('resize', onReposition)
    window.removeEventListener('scroll', onReposition, true)
    window.visualViewport?.removeEventListener('resize', onReposition)
    window.visualViewport?.removeEventListener('scroll', onReposition)
  }
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick, true)
  document.removeEventListener('keydown', onKeydown)
  window.removeEventListener('resize', onReposition)
  window.removeEventListener('scroll', onReposition, true)
  window.visualViewport?.removeEventListener('resize', onReposition)
  window.visualViewport?.removeEventListener('scroll', onReposition)
})

defineExpose({ close })
</script>

<template>
  <span class="inline-flex">
    <span ref="triggerRef" class="inline-flex">
      <slot name="trigger" :open="open" :toggle="toggle">
        <button
          type="button"
          class="grid h-10 w-10 place-items-center rounded-sm text-ink-soft transition-colors duration-fast ease-soft hover:bg-surface-sunken hover:text-ink"
          :class="open ? 'bg-surface-sunken text-ink' : ''"
          :aria-expanded="open"
          :aria-label="label ?? t('common.moreActions')"
          @click.stop="toggle"
        >
          <Icon name="more-horizontal" class="h-4 w-4" />
        </button>
      </slot>
    </span>

    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-fast ease-soft"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-fast ease-soft"
        leave-to-class="opacity-0"
      >
        <div
          v-if="open"
          ref="panelRef"
          class="elevated-overlay fixed z-50 overflow-y-auto bg-surface p-1.5"
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
