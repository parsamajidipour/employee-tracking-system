<script setup lang="ts">
const { locale, t } = useI18n()

useHead({
  title: () => t('app.name'),
  htmlAttrs: {
    lang: () => locale.value,
    dir: () => locale.value === 'ar' ? 'rtl' : 'ltr',
  },
})
</script>

<template>
  <div>
    <NuxtRouteAnnouncer />
    <NuxtLayout>
      <NuxtPage :transition="{ name: 'page' }" />
    </NuxtLayout>
    <ConfirmDialog />
    <ToastContainer />
  </div>
</template>
