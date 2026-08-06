<script setup lang="ts">
const toasts = useToastState()
</script>

<template>
  <Teleport to="body">
    <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="flex items-center gap-2 rounded border bg-white px-3 py-2 text-sm shadow-md"
        :class="toast.variant === 'success' ? 'border-emerald-200 text-emerald-700' : 'border-red-200 text-red-700'"
      >
        <span
          class="inline-block h-1.5 w-1.5 flex-none rounded-full"
          :class="toast.variant === 'success' ? 'bg-emerald-600' : 'bg-red-600'"
        ></span>
        {{ toast.message }}
      </div>
    </div>
  </Teleport>
</template>
