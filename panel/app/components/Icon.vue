<script setup lang="ts">
const ICONS: Record<string, string> = {
  back: 'M15 18l-6-6 6-6',
  forward: 'M9 18l6-6-6-6',
  refresh: 'M4 4v5h5M20 20v-5h-5M4.5 9a7.5 7.5 0 0 1 12.8-4.2L20 7.5M19.5 15a7.5 7.5 0 0 1-12.8 4.2L4 16.5',
  close: 'M6 6l12 12M18 6 6 18',
  'chevron-down': 'M6 9l6 6 6-6',
  'chevron-right': 'M9 6l6 6-6 6',
  menu: 'M4 6h16M4 12h16M4 18h16',
  'map-pin': 'M12 21s-7-5.686-7-11a7 7 0 1 1 14 0c0 5.314-7 11-7 11Z M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
  calendar: 'M8 2v3M16 2v3M3.5 9h17M4.5 5.5h15a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1Z',
  users: 'M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20M9.5 10.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM21 20v-1.5a4 4 0 0 0-3-3.87M16.5 3.87a4 4 0 0 1 0 7.75',
  download: 'M12 3v12m0 0 4-4m-4 4-4-4M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2',
  upload: 'M12 21V9m0 0 4 4m-4-4-4 4M5 7V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2',
  'user-circle': 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0',
  trash: 'M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-8 0 1 13a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-13',
  route: 'M6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM18 9a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM6 15V9a4 4 0 0 1 4-4h1M18 11v2a4 4 0 0 1-4 4h-1',
  speed: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z M12 12l4-3M12 8v1',
  search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM21 21l-4.35-4.35',
  plus: 'M12 5v14M5 12h14',
  key: 'M15 7a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm4 4h5m-2 0v3m-9-3-6 6v3h3l6-6',
  smartphone: 'M8 3h8a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1ZM11 18h2',
  'alert-triangle': 'M10.3 3.9 2.3 18a1 1 0 0 0 .87 1.5h17.7a1 1 0 0 0 .86-1.5L13.7 3.9a1 1 0 0 0-1.73 0ZM12 9v4M12 16.5h.01',
  'check-circle': 'M21 12a9 9 0 1 1-9-9M22 4 12 14.01l-3-3',
  inbox: 'M4 12h4l2 3h4l2-3h4M4 12v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6M4 12l2.5-6.5A1 1 0 0 1 7.4 5h9.2a1 1 0 0 1 .94.65L20 12',
  'more-horizontal': 'M5 12h.01M12 12h.01M19 12h.01',
  command: 'M6 6a2 2 0 1 1 2 2H6V6Zm0 12a2 2 0 1 0 2-2H6v2Zm12-12a2 2 0 1 0-2 2h2V6Zm0 12a2 2 0 1 1-2-2h2v2ZM8 8h8v8H8V8Z',
  lock: 'M6 11V8a6 6 0 1 1 12 0v3M5 11h14a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Z',
  mail: 'M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Zm0 0 8 7 8-7',
  briefcase: 'M4 8h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1ZM9 8V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18',
  'chart-bar': 'M4 20V10M10 20V4M16 20v-7M4 20h16',
  camera: 'M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Zm8 9a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z',
  navigation: 'M3 11l18-8-8 18-2.5-7.5L3 11Z',
  bell: 'M18 8a6 6 0 1 0-12 0c0 6-2 7-2 7h16s-2-1-2-7M13.7 20a2 2 0 0 1-3.4 0',
  filter: 'M3 5h18l-7 8v5l-4 2v-7L3 5Z',
  clock: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-13.5V12l3 2',
  pencil: 'M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z',
  power: 'M12 4v8M7.8 6.8a7 7 0 1 0 8.4 0',
  check: 'M4.5 12.5 9 17l10.5-10.5',
  history: 'M3.5 9A9 9 0 1 1 3 12M3.5 4.5V9H8M12 7.5V12l3 2',
  sparkle: 'M12 3.5 13.8 9l5.7 1.8-5.7 1.8L12 20.5l-1.8-5.9L4.5 12.8 10.2 11 12 3.5Z',
  x_circle: 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9 9l6 6M15 9l-6 6',
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
    class="transition-transform duration-fast ease-soft"
    :class="spin ? 'animate-spin' : ''"
  >
    <path :d="ICONS[name] ?? ''" />
  </svg>
</template>
