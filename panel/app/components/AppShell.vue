<script setup lang="ts">
withDefaults(defineProps<{ title: string; subtitle?: string; fullBleed?: boolean }>(), {
  fullBleed: false,
})

const { open } = useSidebar()
</script>

<template>
  <div class="flex h-dvh overflow-hidden bg-canvas">
    <AppSidebar />

    <div class="flex min-w-0 flex-1 flex-col">
      <header
        class="flex h-16 flex-none items-center justify-between gap-3 border-b border-hairline
               bg-surface px-4 sm:px-6"
      >
        <div class="flex min-w-0 items-center gap-3">
          <button
            type="button"
            class="grid h-10 w-10 flex-none place-items-center rounded-small text-ink-soft transition-colors hover:bg-surface-muted hover:text-ink lg:hidden"
            aria-label="Open menu"
            @click="open"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
              <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <div class="min-w-0">
            <h1 class="truncate text-[19px] font-bold leading-tight sm:text-[22px]">{{ title }}</h1>
            <p v-if="subtitle" class="muted truncate text-xs">{{ subtitle }}</p>
          </div>
        </div>
        <div v-if="$slots.actions" class="flex flex-none items-center gap-2">
          <slot name="actions" />
        </div>
      </header>

      <main :class="fullBleed ? 'relative flex-1 overflow-hidden' : 'flex-1 overflow-y-auto p-4 sm:p-6'">
        <slot />
      </main>
    </div>
  </div>
</template>
