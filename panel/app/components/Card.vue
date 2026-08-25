<script setup lang="ts">
withDefaults(
  defineProps<{
    title?: string
    subtitle?: string
    icon?: string
    flush?: boolean
    scroll?: boolean
  }>(),
  { flush: false, scroll: false },
)
</script>

<template>
  <section class="surface-flat flex min-h-0 flex-col">
    <header
      v-if="title || $slots.actions"
      class="flex flex-none items-center justify-between gap-3 border-b border-hairline px-4 py-3 sm:px-5"
    >
      <div class="flex min-w-0 items-center gap-2.5">
        <span v-if="icon" class="grid h-8 w-8 flex-none place-items-center rounded-sm bg-primary-soft text-primary-strong">
          <Icon :name="icon" class="h-4 w-4" />
        </span>
        <div class="min-w-0">
          <h2 class="truncate">{{ title }}</h2>
          <p v-if="subtitle" class="truncate text-[12px] text-ink-faint">{{ subtitle }}</p>
        </div>
      </div>
      <div v-if="$slots.actions" class="flex flex-none items-center gap-2">
        <slot name="actions" />
      </div>
    </header>

    <div
      class="min-h-0 flex-1"
      :class="[scroll ? 'overflow-y-auto' : '', flush ? '' : 'p-4 sm:p-5']"
    >
      <slot />
    </div>

    <footer v-if="$slots.footer" class="flex flex-none items-center justify-between gap-3 border-t border-hairline px-4 py-3 sm:px-5">
      <slot name="footer" />
    </footer>
  </section>
</template>
