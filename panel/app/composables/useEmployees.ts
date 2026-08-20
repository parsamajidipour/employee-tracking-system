export interface EmployeeDevice {
  device_identifier: string
  device_name: string | null
  last_seen_at: string | null
}

export interface EmployeeShiftSummary {
  id: number
  name: string
  start_time: string
  end_time: string
}

export interface Employee {
  id: number
  name: string
  phone: string | null
  email: string | null
  role: 'admin' | 'hr' | 'supervisor' | 'employee'
  is_active: boolean
  device: EmployeeDevice | null
  shifts: EmployeeShiftSummary[]
}

export function useEmployees() {
  return useCachedResource<Employee[]>('employees', () => apiFetch<Employee[]>('/api/v1/employees'))
}
