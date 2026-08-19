<script setup lang="ts">
withDefaults(defineProps<{ title: string; subtitle?: string; fullBleed?: boolean; backTo?: string }>(), {
  fullBleed: false,
})

const { open } = useSidebar()
</script>

<template>
  <div class="flex h-dvh overflow-hidden bg-canvas">
    <AppSidebar />

    <div class="flex min-w-0 flex-1 flex-col">
      <header
        class="flex h-14 flex-none items-center justify-between gap-3 border-b border-hairline
               bg-surface/90 px-4 backdrop-blur-sm sm:px-5"
      >
        <div class="flex min-w-0 items-center gap-2.5">
          <button
            type="button"
            class="grid h-8 w-8 flex-none place-items-center rounded-sm text-ink-soft transition-colors hover:bg-surface-sunken hover:text-ink lg:hidden"
            aria-label="Open menu"
            @click="open"
          >
            <Icon name="menu" class="h-[18px] w-[18px]" />
          </button>
          <NuxtLink
            v-if="backTo"
            :to="backTo"
            aria-label="Back"
            class="grid h-8 w-8 flex-none place-items-center rounded-sm text-ink-soft transition-colors hover:bg-surface-sunken hover:text-ink"
          >
            <Icon name="back" class="h-[18px] w-[18px]" />
          </NuxtLink>
          <div class="min-w-0">
            <h1 class="truncate">{{ title }}</h1>
            <p v-if="subtitle" class="muted truncate text-[11.5px]">{{ subtitle }}</p>
          </div>
        </div>
        <div v-if="$slots.actions" class="flex flex-none items-center gap-2">
          <slot name="actions" />
        </div>
      </header>

      <main :class="fullBleed ? 'relative flex-1 overflow-hidden' : 'flex-1 overflow-y-auto p-4 sm:p-5'">
        <slot />
      </main>
    </div>
  </div>
</template>
