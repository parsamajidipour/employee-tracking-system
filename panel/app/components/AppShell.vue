<script setup lang="ts">
// The one app shell every page uses: sidebar, page title, action area,
// content. `fullBleed` is for the map page only — no content padding, and
// overflow is clipped instead of scrolling, so the map canvas can fill the
// area edge-to-edge with the side list/detail panel absolutely positioned
// on top of it rather than sitting beside it in normal flow.
withDefaults(defineProps<{ title: string; fullBleed?: boolean }>(), { fullBleed: false })
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-white">
    <AppSidebar />
    <div class="flex flex-1 flex-col overflow-hidden">
      <header class="flex h-14 flex-none items-center justify-between border-b border-slate-200 px-6">
        <h1 class="text-base font-semibold text-slate-900">{{ title }}</h1>
        <div v-if="$slots.actions" class="flex items-center gap-2">
          <slot name="actions" />
        </div>
      </header>
      <main :class="fullBleed ? 'relative flex-1 overflow-hidden' : 'flex-1 overflow-y-auto p-6'">
        <slot />
      </main>
    </div>
  </div>
</template>
