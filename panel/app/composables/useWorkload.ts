export interface WorkloadSummary {
  active_cases: number
  pending: number
  scheduled: number
  overdue: number
  completed_today: number
  completed_week: number
  completed_month: number
  oldest_pending_hours: number | null
}

export interface WorkloadActivity {
  window_minutes: number | null
  distance_m: number
  inspection_minutes: number
  travel_minutes: number
  idle_minutes: number
}

export interface WorkloadRow {
  employee_id: number
  name: string
  summary: WorkloadSummary
  today: WorkloadActivity
}

export interface WorkloadDetail {
  employee_id: number
  name: string
  summary: WorkloadSummary
  activity: WorkloadActivity
}

export function useWorkloadList() {
  const data = ref<WorkloadRow[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function load(): Promise<void> {
    loading.value = true
    try {
      data.value = await apiFetch<WorkloadRow[]>('/api/v1/workload')
      error.value = null
    } catch {
      error.value = 'Could not load workload.'
    } finally {
      loading.value = false
    }
  }

  return { data, loading, error, load }
}

export function fetchWorkloadDetail(employeeId: number, date?: string): Promise<WorkloadDetail> {
  const qs = date ? `?date=${encodeURIComponent(date)}` : ''
  return apiFetch<WorkloadDetail>(`/api/v1/workload/${employeeId}${qs}`)
}
