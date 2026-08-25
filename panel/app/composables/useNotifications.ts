export interface InboxNotification {
  id: string
  type: string
  message: string | null
  case_id: number | null
  reference_no: string | null
  read_at: string | null
  created_at: string
}

interface InboxResponse {
  data: InboxNotification[]
  unread_count: number
}

export function useNotifications() {
  const items = useState<InboxNotification[]>('notifications', () => [])
  const unreadCount = useState<number>('notifications-unread', () => 0)
  const loading = useState<boolean>('notifications-loading', () => false)
  const error = useState<string | null>('notifications-error', () => null)
  const subscribed = useState<boolean>('notifications-subscribed', () => false)

  async function load(): Promise<void> {
    loading.value = true
    try {
      const page = await apiFetch<InboxResponse>('/api/v1/notifications')
      items.value = page.data
      unreadCount.value = page.unread_count
      error.value = null
    } catch {
      error.value = 'Could not load notifications.'
    } finally {
      loading.value = false
    }
  }

  function prepend(notification: InboxNotification): void {
    if (items.value.some((item) => item.id === notification.id)) return
    items.value = [notification, ...items.value].slice(0, 50)
    unreadCount.value += 1
  }

  async function markRead(id: string): Promise<void> {
    const target = items.value.find((item) => item.id === id)
    if (!target || target.read_at) return

    target.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)

    try {
      await apiFetch(`/api/v1/notifications/${id}/read`, { method: 'POST' })
    } catch {
    }
  }

  async function markAllRead(): Promise<void> {
    const now = new Date().toISOString()
    items.value = items.value.map((item) => (item.read_at ? item : { ...item, read_at: now }))
    unreadCount.value = 0

    try {
      await apiFetch('/api/v1/notifications/read-all', { method: 'POST' })
    } catch {
    }
  }

  function subscribe(userId: number): void {
    if (subscribed.value) return

    const echo = sharedEcho()
    if (!echo) return

    try {
      echo.private(`App.Models.User.${userId}`).notification((payload: Record<string, unknown>) => {
        prepend({
          id: String(payload.id ?? crypto.randomUUID()),
          type: String(payload.type ?? 'notification'),
          message: (payload.message as string | undefined) ?? null,
          case_id: (payload.case_id as number | undefined) ?? null,
          reference_no: (payload.reference_no as string | undefined) ?? null,
          read_at: null,
          created_at: new Date().toISOString(),
        })
      })
      subscribed.value = true
    } catch {
    }
  }

  return { items, unreadCount, loading, error, load, markRead, markAllRead, subscribe }
}
