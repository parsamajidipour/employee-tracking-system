export default defineNuxtPlugin(async (nuxtApp) => {
  const savedLocale = useCookie<'en' | 'ar'>('smart_inspection_locale', {
    default: () => 'en',
    maxAge: 60 * 60 * 24 * 365,
    sameSite: 'lax',
  })
  const locale = savedLocale.value === 'ar' ? 'ar' : 'en'

  await nuxtApp.$i18n.setLocale(locale)

  watch(
    nuxtApp.$i18n.locale,
    (value) => {
      const normalized = value === 'ar' ? 'ar' : 'en'
      savedLocale.value = normalized
      document.documentElement.lang = normalized
      document.documentElement.dir = normalized === 'ar' ? 'rtl' : 'ltr'
    },
    { immediate: true },
  )
})
