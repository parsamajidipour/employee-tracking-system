import type Echo from 'laravel-echo'

export interface Position {
  employee_id: number
  name: string
  lat: number
  lng: number
  accuracy_m: number | null
  battery_pct: number | null
  recorded_at: string
  effective_end: string
  distance_today_m: number
  connection_status: 'online' | 'offline'
}

export type StalenessBucket = 'online' | 'offline'

export function usePositions() {
  const positions = ref(new Map<number, Position>())
  const now = ref(Date.now())

  const removalTimers = new Map<number, ReturnType<typeof setTimeout>>()
  let nowTicker: ReturnType<typeof setInterval> | undefined
  let echo: Echo<'reverb'> | undefined

  function clearRemovalTimer(employeeId: number) {
    const timer = removalTimers.get(employeeId)
    if (timer !== undefined) {
      clearTimeout(timer)
      removalTimers.delete(employeeId)
    }
  }

  function scheduleRemoval(position: Position) {
    clearRemovalTimer(position.employee_id)

    const delay = new Date(position.effective_end).getTime() - Date.now()
    removalTimers.set(
      position.employee_id,
      setTimeout(() => {
        positions.value.delete(position.employee_id)
        removalTimers.delete(position.employee_id)
      }, Math.max(0, delay)),
    )
  }

  function applyPosition(position: Position) {
    const existing = positions.value.get(position.employee_id)

    if (existing && new Date(existing.recorded_at).getTime() >= new Date(position.recorded_at).getTime()) {
      return
    }

    positions.value.set(position.employee_id, position)
    scheduleRemoval(position)
  }

  async function fetchSnapshot() {
    const snapshot = await apiFetch<Position[]>('/api/v1/positions')

    for (const employeeId of removalTimers.keys()) clearRemovalTimer(employeeId)

    const next = new Map<number, Position>()
    for (const position of snapshot) {
      next.set(position.employee_id, position)
    }
    positions.value = next

    for (const position of snapshot) scheduleRemoval(position)
  }

  function connect() {
    echo = createEcho()

    echo.private('positions').listen('.position.updated', (payload: Position) => {
      applyPosition(payload)
    })

    echo.connector.onConnectionChange((status) => {
      if (status === 'connected') {
        fetchSnapshot().catch(() => {})
      }
    })
  }

  function stalenessBucket(recordedAt: string): StalenessBucket {
    const ageMs = now.value - new Date(recordedAt).getTime()
    return ageMs < 30_000 ? 'online' : 'offline'
  }

  const sortedPositions = computed(() =>
    Array.from(positions.value.values()).sort((a, b) => a.name.localeCompare(b.name)),
  )

  onMounted(async () => {
    nowTicker = setInterval(() => {
      now.value = Date.now()
    }, 1_000)

    await fetchSnapshot()
    connect()
  })

  onUnmounted(() => {
    echo?.leave('positions')
    echo?.disconnect()
    if (nowTicker !== undefined) clearInterval(nowTicker)
    for (const employeeId of removalTimers.keys()) clearRemovalTimer(employeeId)
  })

  return {
    positions: sortedPositions,
    now,
    stalenessBucket,
  }
}
