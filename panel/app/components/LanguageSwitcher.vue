<script setup lang="ts">
const { locale, setLocale, t } = useI18n()

async function changeLanguage(event: Event) {
  const value = (event.target as HTMLSelectElement).value
  await setLocale(value === 'ar' ? 'ar' : 'en')
}
</script>

<template>
  <label class="relative inline-flex items-center">
    <span class="sr-only">{{ t('language.label') }}</span>
    <Icon name="language" class="pointer-events-none absolute start-2.5 h-4 w-4 text-current" />
    <select
      :value="locale"
      class="h-10 cursor-pointer appearance-none rounded-sm border border-current/20 bg-transparent ps-8 pe-7 text-[12.5px] font-semibold outline-none transition-colors hover:bg-current/10 focus-visible:ring-2 focus-visible:ring-primary"
      :aria-label="t('language.label')"
      @change="changeLanguage"
    >
      <option value="en">{{ t('language.english') }}</option>
      <option value="ar">{{ t('language.arabic') }}</option>
    </select>
    <Icon name="chevron-down" class="pointer-events-none absolute end-2.5 h-3 w-3 text-current" />
  </label>
</template>
