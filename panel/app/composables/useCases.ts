export type CaseStatus = 'pending' | 'accepted' | 'overdue' | 'in_progress' | 'completed' | 'rejected' | 'cancelled'
export type CasePriority = 'normal' | 'high' | 'urgent'

export interface CaseStatusEvent {
  id: number
  actor_name: string | null
  from_status: CaseStatus | null
  to_status: CaseStatus
  note: string | null
  created_at: string
}

export interface CasePhoto {
  id: number
  url: string
  is_gps_verified: boolean
  distance_from_case_m: number | null
  captured_at: string
}

export interface InspectionCase {
  id: number
  reference_no: string
  title: string
  property_address: string | null
  lat: number
  lng: number
  status: CaseStatus
  priority: CasePriority
  assigned_to: number | null
  assignee_name: string | null
  assigned_at: string | null
  accepted_at: string | null
  planned_at: string | null
  started_at: string | null
  completed_at: string | null
  notes: string | null
  created_at: string
  status_events?: CaseStatusEvent[]
  photos?: CasePhoto[]
}

export interface NearestSurveyor {
  employee_id: number
  name: string
  lat: number
  lng: number
  distance_m: number
  open_case_count: number
  connection_status: 'online' | 'offline'
  recorded_at: string
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

export interface CasesPage {
  data: InspectionCase[]
  links: PaginationLinks
  meta: PaginationMeta
}

export interface CaseFilters {
  status?: CaseStatus
  assigned_to?: number
  page?: number
}

export interface NewCasePayload {
  reference_no: string
  title: string
  property_address?: string
  lat: number
  lng: number
  priority?: CasePriority
  notes?: string
  assigned_to?: number
}

function buildQuery(filters: CaseFilters): string {
  const query = new URLSearchParams()
  if (filters.status) query.set('status', filters.status)
  if (filters.assigned_to) query.set('assigned_to', String(filters.assigned_to))
  if (filters.page) query.set('page', String(filters.page))
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export function useCasesList() {
  const data = ref<InspectionCase[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const links = ref<PaginationLinks | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function load(filters: CaseFilters = {}): Promise<void> {
    loading.value = true
    try {
      const page = await apiFetch<CasesPage>(`/api/v1/cases${buildQuery(filters)}`)
      data.value = page.data
      meta.value = page.meta
      links.value = page.links
      error.value = null
    } catch {
      error.value = 'Could not load cases.'
    } finally {
      loading.value = false
    }
  }

  return { data, meta, links, loading, error, load }
}

export function fetchCase(id: number): Promise<InspectionCase> {
  return apiFetch<InspectionCase>(`/api/v1/cases/${id}`)
}

export function createCase(payload: NewCasePayload): Promise<InspectionCase> {
  return apiFetch<InspectionCase>('/api/v1/cases', { method: 'POST', body: payload })
}

export function assignCase(id: number, employeeId: number): Promise<InspectionCase> {
  return apiFetch<InspectionCase>(`/api/v1/cases/${id}/assign`, { method: 'POST', body: { employee_id: employeeId } })
}

export function cancelCase(id: number, note?: string): Promise<InspectionCase> {
  return apiFetch<InspectionCase>(`/api/v1/cases/${id}/cancel`, { method: 'POST', body: note ? { note } : {} })
}

export function deleteCase(id: number): Promise<void> {
  return apiFetch<void>(`/api/v1/cases/${id}`, { method: 'DELETE' })
}

export function fetchNearestSurveyors(id: number): Promise<NearestSurveyor[]> {
  return apiFetch<NearestSurveyor[]>(`/api/v1/cases/${id}/nearest-surveyors`)
}
