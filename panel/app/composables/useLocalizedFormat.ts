export function useLocalizedFormat() {
  const { locale, t } = useI18n()

  const localeName = computed(() => locale.value === 'ar' ? 'ar-OM' : 'en-OM')

  function number(value: number, options?: Intl.NumberFormatOptions): string {
    return new Intl.NumberFormat(localeName.value, options).format(value)
  }

  function date(value: string | Date, options?: Intl.DateTimeFormatOptions): string {
    return new Intl.DateTimeFormat(localeName.value, options).format(new Date(value))
  }

  function dateTime(value: string | Date, options?: Intl.DateTimeFormatOptions): string {
    return date(value, options ?? { dateStyle: 'medium', timeStyle: 'short' })
  }

  function relative(value: string | Date): string {
    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000)
    const formatter = new Intl.RelativeTimeFormat(localeName.value, { numeric: 'auto' })
    if (Math.abs(seconds) < 60) return formatter.format(seconds, 'second')
    const minutes = Math.round(seconds / 60)
    if (Math.abs(minutes) < 60) return formatter.format(minutes, 'minute')
    const hours = Math.round(minutes / 60)
    if (Math.abs(hours) < 24) return formatter.format(hours, 'hour')
    return formatter.format(Math.round(hours / 24), 'day')
  }

  function distance(value: number): string {
    if (value < 1000) return t('units.meter', { value: number(Math.round(value)) })
    return t('units.kilometer', { value: number(value / 1000, { maximumFractionDigits: 1 }) })
  }

  function fileSize(value: number): string {
    if (value < 1024 * 1024) return t('units.kilobyte', { value: number(value / 1024, { maximumFractionDigits: 1 }) })
    return t('units.megabyte', { value: number(value / (1024 * 1024), { maximumFractionDigits: 1 }) })
  }

  return { localeName, number, date, dateTime, relative, distance, fileSize }
}
