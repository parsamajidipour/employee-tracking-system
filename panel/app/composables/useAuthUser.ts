interface AuthUser {
  id: number
  name: string
  email: string
}

/**
 * Shared across the auth middleware and AppSidebar via useState, so a
 * navigation only ever fetches /api/user once, not once per consumer.
 */
export function useAuthUser() {
  const user = useState<AuthUser | null>('auth-user', () => null)

  async function refresh(): Promise<AuthUser | null> {
    try {
      user.value = await apiFetch<AuthUser>('/api/user')
    } catch {
      user.value = null
    }
    return user.value
  }

  return { user, refresh }
}
