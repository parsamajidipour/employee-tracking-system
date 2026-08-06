function readCookie(name) {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'))
  return match ? decodeURIComponent(match[1]) : null
}

export async function ensureCsrfCookie() {
  const { public: { apiBase } } = useRuntimeConfig()
  await $fetch('/sanctum/csrf-cookie', { baseURL: apiBase, credentials: 'include' })
}

export async function apiFetch(path, opts = {}) {
  const { public: { apiBase } } = useRuntimeConfig()
  const xsrfToken = readCookie('XSRF-TOKEN')

  return $fetch(path, {
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
