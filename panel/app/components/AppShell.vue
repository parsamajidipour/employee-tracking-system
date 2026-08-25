<script setup lang="ts">
withDefaults(defineProps<{ title: string; subtitle?: string; fullBleed?: boolean; backTo?: string }>(), {
  fullBleed: false,
})

const { open } = useSidebar()
</script>

<template>
  <header
    class="flex min-h-[72px] flex-none flex-wrap items-center gap-x-2 gap-y-1.5 border-b border-hairline
           bg-surface/90 px-3 py-2 backdrop-blur-sm sm:h-[72px] sm:flex-nowrap sm:gap-3 sm:px-5 sm:py-0 lg:px-6"
  >
    <div class="order-1 flex min-w-0 flex-1 items-center gap-1.5 sm:gap-3">
      <button
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
      class="order-3 flex w-full flex-none items-center justify-end gap-1.5 pt-0.5 sm:order-2 sm:w-auto sm:gap-2.5 sm:pt-0"
    >
      <slot name="actions" />
    </div>
    <span v-if="$slots.actions" class="order-3 mx-0.5 hidden h-6 w-px bg-hairline sm:block" />
    <div class="order-2 flex-none sm:order-4"><NotificationBell /></div>
  </header>

  <main :class="fullBleed ? 'relative flex-1 overflow-hidden' : 'flex-1 overflow-y-auto p-3 sm:p-5 lg:p-7'">
    <slot />
  </main>
</template>
