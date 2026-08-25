<script setup lang="ts">
withDefaults(
  defineProps<{ icon?: string; to?: string; href?: string; tone?: 'default' | 'danger' }>(),
  { tone: 'default' },
)

const classes = computed(() => [
  'flex w-full items-center gap-2.5 rounded-sm px-2.5 py-2.5 text-left text-[13.5px] font-medium transition-colors duration-fast ease-soft',
])
</script>

<template>
  <NuxtLink v-if="to" :to="to" :class="[classes, 'text-ink hover:bg-surface-sunken']">
    <Icon v-if="icon" :name="icon" class="h-4 w-4 flex-none text-ink-faint" />
    <slot />
  </NuxtLink>
  <a v-else-if="href" :href="href" :class="[classes, 'text-ink hover:bg-surface-sunken']">
    <Icon v-if="icon" :name="icon" class="h-4 w-4 flex-none text-ink-faint" />
    <slot />
  </a>
  <button
    v-else
    type="button"
    :class="[classes, tone === 'danger' ? 'text-ink-soft hover:bg-state-danger-soft hover:text-state-danger' : 'text-ink hover:bg-surface-sunken']"
  >
    <Icon v-if="icon" :name="icon" class="h-4 w-4 flex-none" :class="tone === 'danger' ? 'text-state-danger' : 'text-ink-faint'" />
    <slot />
  </button>
</template>
