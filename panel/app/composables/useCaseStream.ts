import type { InspectionCase } from '~/composables/useCases'

export type CaseChangeAction =
  | 'created'
  | 'assigned'
  | 'accepted'
  | 'rejected'
  | 'started'
  | 'completed'
  | 'cancelled'
  | 'deleted'

export interface CaseChangedPayload {
  action: CaseChangeAction
  case_id: number
  case: InspectionCase | null
}

const CHANNEL = 'cases'
const EVENT = '.case.changed'

export function useCaseStream(handler: (payload: CaseChangedPayload) => void) {
  const connected = ref(false)

  onMounted(() => {
    const echo = sharedEcho()
    if (!echo) return

    try {
      echo.private(CHANNEL).listen(EVENT, handler)
      connected.value = true
    } catch {
      connected.value = false
    }
  })

  onUnmounted(() => {
    if (!connected.value) return

    try {
      sharedEcho()?.private(CHANNEL).stopListening(EVENT, handler)
    } catch {
    }
  })

  return { connected }
}
