<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()
const { locale, t } = useI18n()
const notFound = computed(() => props.error.statusCode === 404)

useHead({
  title: () => `${notFound.value ? t('errors.notFoundTitle') : t('errors.serverTitle')} · ${t('app.name')}`,
  htmlAttrs: {
    lang: () => locale.value,
    dir: () => locale.value === 'ar' ? 'rtl' : 'ltr',
  },
})
</script>

<template>
  <main class="safe-screen grid min-h-dvh place-items-center bg-canvas">
    <section class="surface w-full max-w-lg p-6 text-center sm:p-9">
      <div class="mb-5 flex justify-end">
        <LanguageSwitcher class="w-36" />
      </div>
      <p class="tabular text-[42px] font-bold leading-none text-primary">{{ error.statusCode }}</p>
      <h1 class="mt-4 text-[24px] font-semibold text-ink">
        {{ notFound ? t('errors.notFoundTitle') : t('errors.serverTitle') }}
      </h1>
      <p class="mx-auto mt-2 max-w-sm text-[14px] leading-6 text-ink-soft">
        {{ notFound ? t('errors.notFoundMessage') : t('errors.serverMessage') }}
      </p>
      <div class="mt-6 flex flex-wrap justify-center gap-2.5">
        <Button variant="secondary" @click="clearError({ redirect: '/' })">{{ t('errors.backHome') }}</Button>
        <Button v-if="!notFound" @click="clearError()">{{ t('errors.retry') }}</Button>
      </div>
    </section>
  </main>
</template>
