<script setup lang="ts">
withDefaults(defineProps<{ title: string; subtitle?: string; fullBleed?: boolean }>(), {
  fullBleed: false,
})
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-canvas">
    <AppSidebar />

    <div class="flex min-w-0 flex-1 flex-col">
      <header
        class="flex h-16 flex-none items-center justify-between gap-4 border-b border-hairline
               bg-surface px-6"
      >
        <div class="min-w-0">
          <h1 class="truncate text-[17px] font-semibold leading-tight">{{ title }}</h1>
          <p v-if="subtitle" class="muted truncate text-xs">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-none items-center gap-2">
          <slot name="actions" />
        </div>
      </header>

      <main :class="fullBleed ? 'relative flex-1 overflow-hidden' : 'flex-1 overflow-y-auto p-6'">
        <slot />
      </main>
    </div>
  </div>
</template>
