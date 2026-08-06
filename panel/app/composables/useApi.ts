function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'))
  const value = match?.[1]
  return value !== undefined ? decodeURIComponent(value) : null
}

export async function ensureCsrfCookie(): Promise<void> {
  const { public: { apiBase } } = useRuntimeConfig()
  await $fetch('/sanctum/csrf-cookie', { baseURL: apiBase, credentials: 'include' })
}

export async function apiFetch<T>(path: string, opts: Record<string, any> = {}): Promise<T> {
  const { public: { apiBase } } = useRuntimeConfig()
  const xsrfToken = readCookie('XSRF-TOKEN')

  return $fetch<T>(path, {
    baseURL: apiBase,
    credentials: 'include',
    ...opts,
    headers: {
      Accept: 'application/json',
      ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
      ...(opts.headers || {}),
    },
  })
}
