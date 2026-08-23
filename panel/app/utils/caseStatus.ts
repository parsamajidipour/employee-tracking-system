import type { CasePriority, CaseStatus } from '~/composables/useCases'

type BadgeVariant = 'neutral' | 'success' | 'warning' | 'danger'

const STATUS_LABEL: Record<CaseStatus, string> = {
  pending: 'Pending',
  accepted: 'Accepted',
  in_progress: 'In progress',
  completed: 'Completed',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
}

const STATUS_VARIANT: Record<CaseStatus, BadgeVariant> = {
  pending: 'neutral',
  accepted: 'neutral',
  in_progress: 'warning',
  completed: 'success',
  rejected: 'danger',
  cancelled: 'neutral',
}

const PRIORITY_LABEL: Record<CasePriority, string> = {
  normal: 'Normal',
  high: 'High',
  urgent: 'Urgent',
}

const PRIORITY_VARIANT: Record<CasePriority, BadgeVariant> = {
  normal: 'neutral',
  high: 'warning',
  urgent: 'danger',
}

export function caseStatusLabel(status: CaseStatus): string {
  return STATUS_LABEL[status]
}

export function caseStatusVariant(status: CaseStatus): BadgeVariant {
  return STATUS_VARIANT[status]
}

export function casePriorityLabel(priority: CasePriority): string {
  return PRIORITY_LABEL[priority]
}

export function casePriorityVariant(priority: CasePriority): BadgeVariant {
  return PRIORITY_VARIANT[priority]
}

export const CASE_STATUSES: CaseStatus[] = ['pending', 'accepted', 'in_progress', 'completed', 'rejected', 'cancelled']
export const CASE_PRIORITIES: CasePriority[] = ['normal', 'high', 'urgent']
