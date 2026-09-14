export function useTranslatedArray(key: string) {
  const { tm, rt } = useI18n()

  return computed(() => {
    const values = tm(key)
    return Array.isArray(values) ? values.map(value => rt(value)) : []
  })
}
