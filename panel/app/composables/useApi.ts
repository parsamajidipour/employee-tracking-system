function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'))
  const value = match?.[1]
  return value !== undefined ? decodeURIComponent(value) : null
}

export async function ensureCsrfCookie(): Promise<void> {
  await $fetch('/sanctum/csrf-cookie', { baseURL: apiOrigin(), credentials: 'include' })
}

export async function apiFetch<T>(path: string, opts: Record<string, any> = {}): Promise<T> {
  const xsrfToken = readCookie('XSRF-TOKEN')

  return $fetch<T>(path, {
    baseURL: apiOrigin(),
    credentials: 'include',
    ...opts,
    headers: {
      Accept: 'application/json',
      ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
      ...(opts.headers || {}),
    },
  })
}
