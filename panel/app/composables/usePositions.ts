import type Echo from 'laravel-echo'

export interface Position {
  employee_id: number
  name: string
  team_name: string | null
  lat: number
  lng: number
  accuracy_m: number | null
  battery_pct: number | null
  recorded_at: string
  effective_end: string
}

export type StalenessBucket = 'fresh' | 'aging' | 'stale'

/**
 * Drives the live map: fetches the GET /api/v1/positions snapshot, then
 * subscribes to the private `positions` channel for deltas — see
 * CLAUDE.md/SPEC.md section 5. Every reconnect (including the first
 * connect) re-fetches the snapshot, since any delta missed while
 * disconnected is otherwise gone for good.
 *
 * Each position carries its own window's effective_end; a marker is removed
 * with its own exactly-timed setTimeout, not a coarse poll — the close job
 * on the server runs every 5 minutes, and a marker must not outlive its
 * window by even that long.
 */
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
    // Defensive, mirroring the server's own rule (App\Services\
    // PositionPublisher): never let an out-of-order delta move a marker
    // backwards. The server already guarantees this for what it broadcasts,
    // but costs nothing to also hold here.
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

    // Fires on the first connect too — an extra snapshot fetch right after
    // the explicit one below is a harmless no-op at this project's scale,
    // and it means "just connected" and "reconnected" share one code path.
    echo.connector.onConnectionChange((status) => {
      if (status === 'connected') {
        fetchSnapshot().catch(() => {})
      }
    })
  }

  function stalenessBucket(recordedAt: string): StalenessBucket {
    const ageMs = now.value - new Date(recordedAt).getTime()
    if (ageMs < 60_000) return 'fresh'
    if (ageMs < 5 * 60_000) return 'aging'
    return 'stale'
  }

  const sortedPositions = computed(() =>
    Array.from(positions.value.values()).sort((a, b) => a.name.localeCompare(b.name)),
  )

  onMounted(async () => {
    nowTicker = setInterval(() => {
      now.value = Date.now()
    }, 10_000)

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
