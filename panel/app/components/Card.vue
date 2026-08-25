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
      class="flex flex-none flex-wrap items-start justify-between gap-2.5 border-b border-hairline px-3.5 py-3 sm:flex-nowrap sm:px-5"
    >
      <div class="flex min-w-0 items-center gap-2.5">
        <span v-if="icon" class="grid h-8 w-8 flex-none place-items-center rounded-sm bg-primary-soft text-primary-strong">
          <Icon :name="icon" class="h-4 w-4" />
        </span>
        <div class="min-w-0">
          <h2 class="line-clamp-2 sm:truncate">{{ title }}</h2>
          <p v-if="subtitle" class="line-clamp-2 text-[12px] leading-4 text-ink-faint sm:truncate">{{ subtitle }}</p>
        </div>
      </div>
      <div v-if="$slots.actions" class="flex flex-none items-center gap-2 max-[359px]:w-full max-[359px]:justify-end">
        <slot name="actions" />
      </div>
    </header>

    <div
      class="min-h-0 flex-1"
      :class="[scroll ? 'overflow-y-auto' : '', flush ? '' : 'p-3.5 sm:p-5']"
    >
      <slot />
    </div>

    <footer v-if="$slots.footer" class="flex flex-none flex-wrap items-center justify-between gap-2.5 border-t border-hairline px-3.5 py-3 sm:px-5">
      <slot name="footer" />
    </footer>
  </section>
</template>
