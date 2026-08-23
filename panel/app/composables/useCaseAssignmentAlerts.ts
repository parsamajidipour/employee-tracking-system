import type Echo from 'laravel-echo'

interface CaseAssignedNotification {
  type?: string
  case_id?: number
  reference_no?: string
  title?: string
}

export function useCaseAssignmentAlerts(onAssigned: () => void) {
  const toast = useToast()
  const { user, refresh } = useAuthUser()

  let echo: Echo<'reverb'> | undefined
  let pollTimer: ReturnType<typeof setInterval> | undefined

  async function connect() {
    if (!user.value) await refresh()
    if (!user.value || (user.value.role !== 'admin' && user.value.role !== 'supervisor')) return

    try {
      echo = createEcho()
      echo.private(`App.Models.User.${user.value.id}`).notification((notification: CaseAssignedNotification) => {
        if (notification.type === 'case.assigned') {
          toast.success(notification.title ? `New case assigned — ${notification.title}` : 'New case assigned')
          onAssigned()
        }
      })
    } catch {
    }
  }

  onMounted(() => {
    connect()
    pollTimer = setInterval(onAssigned, 45_000)
  })

  onUnmounted(() => {
    if (user.value) echo?.leave(`App.Models.User.${user.value.id}`)
    echo?.disconnect()
    if (pollTimer !== undefined) clearInterval(pollTimer)
  })
}
