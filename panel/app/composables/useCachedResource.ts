export function useCachedResource<T>(key: string, fetcher: () => Promise<T>) {
  const data = useState<T | null>(`cache-${key}`, () => null)
  const loading = useState<boolean>(`cache-${key}-loading`, () => false)
  const error = useState<string | null>(`cache-${key}-error`, () => null)

  async function load(force = false): Promise<void> {
    if (data.value !== null && !force) return

    loading.value = true
    try {
      data.value = await fetcher()
      error.value = null
    } catch {
      error.value = 'Could not load data.'
    } finally {
      loading.value = false
    }
  }

  function refresh(): Promise<void> {
    return load(true)
  }

  return { data, loading, error, load, refresh }
}
