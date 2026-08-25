<script setup lang="ts">
withDefaults(defineProps<{ title: string; subtitle?: string; fullBleed?: boolean; backTo?: string }>(), {
  fullBleed: false,
})

const { open } = useSidebar()
</script>

<template>
  <header
    class="flex h-16 flex-none items-center gap-1.5 border-b border-hairline bg-surface/90 px-3
           backdrop-blur-sm sm:h-[72px] sm:gap-3 sm:px-5 lg:px-6"
  >
    <div class="flex min-w-0 flex-1 items-center gap-1 sm:gap-3">
      <button
        v-if="!backTo"
        type="button"
        class="grid h-10 w-10 flex-none place-items-center rounded-sm text-ink-soft transition-colors hover:bg-surface-sunken hover:text-ink lg:hidden"
        aria-label="Open menu"
        @click="open"
      >
        <Icon name="menu" class="h-5 w-5" />
      </button>
      <NuxtLink
        v-if="backTo"
        :to="backTo"
        aria-label="Back"
        class="grid h-10 w-10 flex-none place-items-center rounded-sm text-ink-soft transition-colors hover:bg-surface-sunken hover:text-ink"
      >
        <Icon name="back" class="h-5 w-5" />
      </NuxtLink>
      <div class="min-w-0">
        <h1 class="truncate text-[17px] leading-tight sm:text-[20px] lg:text-[24px] lg:leading-[30px]">{{ title }}</h1>
        <p v-if="subtitle" class="muted truncate text-[11px] sm:text-[12.5px]">{{ subtitle }}</p>
      </div>
    </div>
    <div
      v-if="$slots.actions"
      class="flex flex-none items-center justify-end gap-1 sm:gap-2.5"
    >
      <slot name="actions" />
    </div>
    <span v-if="$slots.actions" class="mx-0.5 hidden h-6 w-px bg-hairline sm:block" />
    <div class="flex-none"><NotificationBell /></div>
  </header>

  <main :class="fullBleed ? 'relative min-h-0 flex-1 overflow-hidden' : 'min-h-0 flex-1 overflow-y-auto p-3 sm:p-5 lg:p-7'">
    <slot />
  </main>
</template>
