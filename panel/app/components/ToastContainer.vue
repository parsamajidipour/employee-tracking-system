<script setup lang="ts">
const toasts = useToastState()
</script>

<template>
  <Teleport to="body">
    <div class="toast-viewport fixed z-50 flex flex-col gap-2">
      <TransitionGroup
        enter-active-class="transition duration-base ease-soft"
        enter-from-class="opacity-0 translate-x-4"
        leave-active-class="transition duration-fast ease-soft absolute"
        leave-to-class="opacity-0"
        move-class="transition duration-base ease-soft"
      >
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="elevated-overlay flex items-center gap-2.5 bg-surface px-3.5 py-3 text-[13px] font-medium"
          :class="toast.variant === 'success' ? 'border-s-4 !border-s-state-success text-state-success' : 'border-s-4 !border-s-state-danger text-state-danger'"
        >
          <Icon :name="toast.variant === 'success' ? 'check-circle' : 'alert-triangle'" class="h-4 w-4 flex-none" />
          {{ toast.message }}
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
