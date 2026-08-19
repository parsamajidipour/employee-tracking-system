export interface ShiftTemplate {
  id: number
  name: string
  days_of_week: number[]
  start_time: string
  end_time: string
  grace_before_min: number
  grace_after_min: number
  max_daily_minutes: number | null
}

export function useShiftTemplates() {
  return useCachedResource<ShiftTemplate[]>('shift-templates', () => apiFetch<ShiftTemplate[]>('/api/v1/shift-templates'))
}
