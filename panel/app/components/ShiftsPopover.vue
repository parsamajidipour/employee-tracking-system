<script setup lang="ts">
import type { EmployeeShiftSummary } from '~/composables/useEmployees'

const props = defineProps<{ shifts: EmployeeShiftSummary[] }>()

const open = ref(false)
const triggerRef = ref<HTMLButtonElement | null>(null)
const popoverRef = ref<HTMLElement | null>(null)
const position = ref({ top: 0, left: 0 })

const MARGIN = 8

function updatePosition() {
  const trigger = triggerRef.value
  if (!trigger) return

  const rect = trigger.getBoundingClientRect()
  const width = Math.min(272, window.innerWidth - MARGIN * 2)
  const estimatedHeight = Math.min(320, props.shifts.length * 30 + 44)

  let left = rect.right - width
  left = Math.max(MARGIN, Math.min(left, window.innerWidth - width - MARGIN))

  let top = rect.bottom + 6
  if (top + estimatedHeight > window.innerHeight - MARGIN) {
    top = Math.max(MARGIN, rect.top - estimatedHeight - 6)
  }

  position.value = { top, left }
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
  if (popoverRef.value?.contains(target) || triggerRef.value?.contains(target)) return
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
</script>

<template>
  <span class="inline-flex items-center gap-1.5">
    <span class="tabular text-[13.5px] text-ink">{{ shifts.length }} {{ shifts.length === 1 ? 'shift' : 'shifts' }}</span>
    <button
      ref="triggerRef"
      type="button"
      class="grid h-6 w-6 shrink-0 place-items-center rounded-sm text-ink-faint transition-colors hover:bg-surface-sunken hover:text-primary-strong"
      :class="open ? 'bg-surface-sunken text-primary-strong' : ''"
      :aria-expanded="open"
      aria-label="Show shift details"
      @click.stop="toggle"
    >
      <Icon name="calendar" class="h-3.5 w-3.5" />
    </button>

    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-fast ease-soft"
        enter-from-class="opacity-0 scale-95"
        leave-active-class="transition duration-fast ease-soft"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="open"
          ref="popoverRef"
          role="tooltip"
          class="surface fixed z-50 max-h-80 overflow-y-auto p-3"
          :style="{ top: `${position.top}px`, left: `${position.left}px`, width: `min(272px, calc(100vw - ${MARGIN * 2}px))` }"
        >
          <p class="eyebrow mb-2">{{ shifts.length }} {{ shifts.length === 1 ? 'shift' : 'shifts' }} assigned</p>
          <ul class="space-y-1.5">
            <li v-for="shift in shifts" :key="shift.id" class="flex items-center justify-between gap-3 text-[13px]">
              <span class="min-w-0 truncate font-medium text-ink">{{ shift.name }}</span>
              <span class="shrink-0 tabular text-ink-soft">{{ shift.start_time.slice(0, 5) }}–{{ shift.end_time.slice(0, 5) }}</span>
            </li>
          </ul>
        </div>
      </Transition>
    </Teleport>
  </span>
</template>
