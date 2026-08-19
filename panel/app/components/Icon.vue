<script setup lang="ts">
const ICONS: Record<string, string> = {
  back: 'M15 18l-6-6 6-6',
  refresh: 'M4 4v5h5M20 20v-5h-5M4.5 9a7.5 7.5 0 0 1 12.8-4.2L20 7.5M19.5 15a7.5 7.5 0 0 1-12.8 4.2L4 16.5',
  close: 'M6 6l12 12M18 6 6 18',
  'chevron-down': 'M6 9l6 6 6-6',
  menu: 'M4 6h16M4 12h16M4 18h16',
  sun: 'M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41 M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
  moon: 'M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z',
  'map-pin': 'M12 21s-7-5.686-7-11a7 7 0 1 1 14 0c0 5.314-7 11-7 11Z M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
  calendar: 'M8 2v3M16 2v3M3.5 9h17M4.5 5.5h15a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1Z',
  users: 'M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20M9.5 10.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM21 20v-1.5a4 4 0 0 0-3-3.87M16.5 3.87a4 4 0 0 1 0 7.75',
  download: 'M12 3v12m0 0 4-4m-4 4-4-4M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2',
  'user-circle': 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0',
  trash: 'M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-8 0 1 13a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-13',
  route: 'M6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM18 9a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM6 15V9a4 4 0 0 1 4-4h1M18 11v2a4 4 0 0 1-4 4h-1',
  speed: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z M12 12l4-3M12 8v1',
}

withDefaults(defineProps<{ name: keyof typeof ICONS | string; spin?: boolean }>(), { spin: false })
</script>

<template>
  <svg
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.75"
    stroke-linecap="round"
    stroke-linejoin="round"
    class="transition-transform duration-150 ease-soft"
    :class="spin ? 'animate-spin' : ''"
  >
    <path :d="ICONS[name] ?? ''" />
  </svg>
</template>
