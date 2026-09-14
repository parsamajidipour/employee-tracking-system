import type { CasePriority, CaseStatus, InspectionCase } from '~/composables/useCases'

type BadgeVariant = 'neutral' | 'success' | 'warning' | 'danger'

const STATUS_VARIANT: Record<CaseStatus, BadgeVariant> = {
  pending: 'neutral',
  accepted: 'neutral',
  overdue: 'danger',
  in_progress: 'warning',
  completed: 'success',
  rejected: 'danger',
  cancelled: 'neutral',
}

const PRIORITY_VARIANT: Record<CasePriority, BadgeVariant> = {
  normal: 'neutral',
  high: 'warning',
  urgent: 'danger',
}

export function caseStatusVariant(status: CaseStatus): BadgeVariant {
  return STATUS_VARIANT[status]
}

export function casePriorityVariant(priority: CasePriority): BadgeVariant {
  return PRIORITY_VARIANT[priority]
}

export const CASE_STATUSES: CaseStatus[] = ['pending', 'accepted', 'overdue', 'in_progress', 'completed', 'rejected', 'cancelled']
export const CASE_PRIORITIES: CasePriority[] = ['normal', 'high', 'urgent']

export type AssignmentDisplayStatus =
  | 'unassigned'
  | 'awaiting_acceptance'
  | 'scheduled'
  | 'in_progress'
  | 'completed'
  | 'rejected'
  | 'cancelled'
  | 'overdue'

export interface AssignmentDisplay {
  status: AssignmentDisplayStatus
  variant: BadgeVariant
  isOverdue: boolean
}

type CaseForAssignmentDisplay = Pick<InspectionCase, 'status' | 'assigned_to' | 'offered_to' | 'planned_at' | 'started_at'>

const ASSIGNMENT_VARIANT: Record<AssignmentDisplayStatus, BadgeVariant> = {
  unassigned: 'neutral',
  awaiting_acceptance: 'warning',
  scheduled: 'neutral',
  in_progress: 'warning',
  completed: 'success',
  rejected: 'danger',
  cancelled: 'neutral',
  overdue: 'danger',
}

export function caseAssignmentDisplay(item: CaseForAssignmentDisplay): AssignmentDisplay {
  const status = deriveAssignmentStatus(item)
  return {
    status,
    variant: ASSIGNMENT_VARIANT[status],
    isOverdue: status === 'overdue',
  }
}

function deriveAssignmentStatus(item: CaseForAssignmentDisplay): AssignmentDisplayStatus {
  if (item.status === 'rejected') return 'rejected'
  if (item.status === 'cancelled') return 'cancelled'
  if (item.status === 'completed') return 'completed'
  if (item.status === 'in_progress') return 'in_progress'
  if (item.status === 'overdue') return 'overdue'

  if (item.status === 'accepted') {
    if (item.planned_at && new Date(item.planned_at).getTime() < Date.now() && !item.started_at) {
      return 'overdue'
    }
    return 'scheduled'
  }

  if (item.status === 'pending') {
    return item.assigned_to || item.offered_to?.length ? 'awaiting_acceptance' : 'unassigned'
  }

  return 'unassigned'
}
