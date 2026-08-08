interface AuthUser {
  id: number
  name: string
  email: string
}

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
