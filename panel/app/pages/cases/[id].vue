<script setup lang="ts">
import type { CaseStatusEvent, InspectionCase, NearestSurveyor } from '~/composables/useCases'
import { caseAssignmentDisplay, casePriorityLabel, casePriorityVariant } from '~/utils/caseStatus'
import { formatDistance } from '~/utils/formatDistance'

const route = useRoute()
const caseId = Number(route.params.id)
const toast = useToast()
const { confirm } = useConfirm()
const { data: employeesData, load: loadEmployees } = useEmployees()
const { data: workloadData, load: loadWorkload } = useWorkloadList()

const item = ref<InspectionCase | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const candidates = ref<NearestSurveyor[]>([])
const candidatesLoading = ref(false)
const candidatesError = ref<string | null>(null)
const selectedSurveyorId = ref<number | null>(null)
const assigning = ref(false)
const cancelModalOpen = ref(false)
const cancelNote = ref('')
const cancelling = ref(false)
const deleting = ref(false)

const assignment = computed(() => item.value ? caseAssignmentDisplay(item.value) : null)
const canAssign = computed(() => item.value?.status === 'rejected' || (item.value?.status === 'pending' && item.value.assigned_to === null))
const canCancel = computed(() => item.value ? ['pending', 'accepted', 'in_progress'].includes(item.value.status) : false)
const canDelete = computed(() => item.value !== null)
const workloadByEmployee = computed(() => new Map(workloadData.value.map(row => [row.employee_id, row])))
const candidateByEmployee = computed(() => new Map(candidates.value.map(row => [row.employee_id, row])))
const activeEmployees = computed(() => (employeesData.value ?? []).filter(employee => employee.is_active))
const surveyorChoices = computed(() => activeEmployees.value
  .map((employee) => {
    const nearby = candidateByEmployee.value.get(employee.id)
    const workload = workloadByEmployee.value.get(employee.id)
    const activeCases = workload?.summary.active_cases ?? nearby?.open_case_count ?? 0
    const workloadPercent = Math.min(100, Math.round((activeCases / 6) * 100))
    return { employee, nearby, workload, activeCases, workloadPercent }
  })
  .sort((a, b) => {
    if (a.nearby && !b.nearby) return -1
    if (!a.nearby && b.nearby) return 1
    if (a.nearby && b.nearby) return a.nearby.distance_m - b.nearby.distance_m
    return a.activeCases - b.activeCases
  }))
const selectedChoice = computed(() => surveyorChoices.value.find(row => row.employee.id === selectedSurveyorId.value) ?? null)
const workflowSteps = computed(() => {
  const current = assignment.value?.status ?? 'unassigned'
  const order = ['unassigned', 'awaiting_acceptance', 'scheduled', 'in_progress', 'completed']
  const currentIndex = order.indexOf(current)
  return [
    { key: 'unassigned', label: 'Received', compactLabel: 'New' },
    { key: 'awaiting_acceptance', label: 'Assigned', compactLabel: 'Assigned' },
    { key: 'scheduled', label: 'Scheduled', compactLabel: 'Plan' },
    { key: 'in_progress', label: 'Inspection', compactLabel: 'Inspect' },
    { key: 'completed', label: 'Completed', compactLabel: 'Done' },
  ].map((step, index) => ({ ...step, done: current === 'completed' || (currentIndex >= 0 && index < currentIndex), active: step.key === current }))
})

async function loadCase() {
  loading.value = true
  try {
    item.value = await fetchCase(caseId)
    error.value = null
  } catch {
    error.value = 'Could not load this case.'
  } finally {
    loading.value = false
  }
}

async function loadCandidates() {
  candidatesLoading.value = true
  try {
    candidates.value = await fetchNearestSurveyors(caseId)
    candidatesError.value = null
  } catch {
    candidatesError.value = 'Live location ranking is temporarily unavailable.'
  } finally {
    candidatesLoading.value = false
  }
}

async function refreshAll() {
  await Promise.all([loadCase(), loadCandidates(), loadEmployees(), loadWorkload()])
}

async function assignSelected() {
  if (!selectedSurveyorId.value || !selectedChoice.value) {
    toast.error('Select an available surveyor first.')
    return
  }
  assigning.value = true
  try {
    await assignCase(caseId, selectedSurveyorId.value)
    toast.success(`Case assigned to ${selectedChoice.value.employee.name}. Awaiting acceptance.`)
    selectedSurveyorId.value = null
    await refreshAll()
  } catch (err) {
    toast.error(apiErrorMessage(err, 'Assignment could not be completed. Nothing was changed.'))
    await refreshAll()
  } finally {
    assigning.value = false
  }
}

async function assignFromMap(employeeId: number) {
  selectedSurveyorId.value = employeeId
  await nextTick()
  await assignSelected()
}

function openCancelModal() {
  cancelNote.value = ''
  cancelModalOpen.value = true
}

async function submitCancel() {
  cancelling.value = true
  try {
    await cancelCase(caseId, cancelNote.value || undefined)
    toast.success('Case cancelled. Relevant users were notified.')
    cancelModalOpen.value = false
    await refreshAll()
  } catch (err) {
    toast.error(apiErrorMessage(err, 'Case could not be cancelled. Nothing was changed.'))
  } finally {
    cancelling.value = false
  }
}

async function remove() {
  const confirmed = await confirm(
    `Permanently delete case "${item.value?.title}" and its timeline and site photos? This cannot be undone.`,
    { title: 'Permanently delete case', variant: 'danger' },
  )
  if (!confirmed) return
  deleting.value = true
  try {
    await deleteCase(caseId)
    toast.success('Case and all related inspection data were permanently deleted.')
    await navigateTo('/cases')
  } catch (err) {
    toast.error(apiErrorMessage(err, 'Case could not be deleted.'))
    deleting.value = false
  }
}

function dateTimeLabel(value: string | null): string {
  return value ? new Date(value).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : 'Not set yet'
}

function shortDateTimeLabel(value: string | null): string {
  return value ? new Date(value).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Not set'
}

function photoDistanceLabel(distance: number | null): string {
  if (distance === null) return 'Distance unavailable'
  return `${formatDistance(distance)} from property`
}

function eventTitle(event: CaseStatusEvent): string {
  if (event.to_status === 'pending' && event.note?.toLowerCase().includes('assigned')) return 'Surveyor assigned'
  if (!event.from_status && event.to_status === 'pending') return 'Case received'
  if (event.to_status === 'accepted') return 'Assignment accepted and scheduled'
  if (event.to_status === 'in_progress') return 'Inspection started'
  if (event.to_status === 'overdue') return 'Inspection became overdue'
  if (event.to_status === 'completed') return 'Inspection completed'
  if (event.to_status === 'rejected') return 'Assignment rejected'
  if (event.to_status === 'cancelled') return 'Case cancelled'
  return 'Case updated'
}

function eventTone(event: CaseStatusEvent): string {
  if (event.to_status === 'completed') return 'bg-state-success'
  if (event.to_status === 'rejected' || event.to_status === 'cancelled' || event.to_status === 'overdue') return 'bg-state-danger'
  if (event.to_status === 'in_progress' || event.to_status === 'accepted') return 'bg-primary'
  return 'bg-state-neutral'
}

useCaseStream((payload) => {
  if (payload.case_id !== caseId) return
  if (payload.action === 'deleted') {
    toast.error('This case was deleted in another session.')
    navigateTo('/cases')
    return
  }
  refreshAll()
})

onMounted(refreshAll)
</script>

<template>
  <AppShell :title="item?.title ?? 'Case workspace'" :subtitle="item?.reference_no" back-to="/cases" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" aria-label="Refresh case" @click="refreshAll">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
    </template>

    <div v-if="loading && !item" class="grid h-full grid-cols-12 gap-4 p-5">
      <Skeleton class="col-span-12 h-24" rounded="md" />
      <Skeleton class="col-span-7 h-full" rounded="md" />
      <Skeleton class="col-span-5 h-full" rounded="md" />
    </div>
    <InlineAlert v-else-if="error" class="m-5">{{ error }}</InlineAlert>

    <div v-else-if="item" class="flex h-full min-h-0 flex-col gap-3 overflow-y-auto p-3 sm:gap-4 lg:overflow-hidden lg:p-5">
      <section class="surface flex-none px-3.5 py-3.5 sm:px-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
          <div class="flex min-w-0 items-center gap-3">
            <span class="grid h-10 w-10 flex-none place-items-center rounded-md bg-primary-soft text-primary-strong"><Icon name="briefcase" class="h-5 w-5" /></span>
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <Badge v-if="assignment" :variant="assignment.variant">{{ assignment.label }}</Badge>
                <Badge :variant="casePriorityVariant(item.priority)">{{ casePriorityLabel(item.priority) }} priority</Badge>
              </div>
              <p class="mt-1 truncate text-[13px] text-ink-soft">{{ item.property_address || 'Property address not provided' }}</p>
            </div>
          </div>

          <ol class="grid min-w-0 flex-1 grid-cols-5 xl:max-w-2xl">
            <li v-for="(step, index) in workflowSteps" :key="step.key" class="relative flex min-w-0 flex-col items-center">
              <span v-if="index" class="absolute right-1/2 top-3 h-px w-full" :class="step.done || step.active ? 'bg-primary' : 'bg-hairline'" />
              <span class="relative z-10 grid h-6 w-6 place-items-center rounded-full border-2 text-[10px] font-bold" :class="step.done ? 'border-primary bg-primary text-white' : step.active ? 'border-primary bg-primary-soft text-primary-strong' : 'border-hairline bg-surface text-ink-faint'">
                <Icon v-if="step.done" name="check" class="h-3 w-3" /><span v-else>{{ index + 1 }}</span>
              </span>
              <span class="mt-1.5 max-w-full truncate px-0.5 text-[9.5px] font-medium min-[420px]:text-[10.5px]" :class="step.active || step.done ? 'text-ink' : 'text-ink-faint'">
                <span class="min-[420px]:hidden">{{ step.compactLabel }}</span>
                <span class="hidden min-[420px]:inline">{{ step.label }}</span>
              </span>
            </li>
          </ol>

          <div class="flex flex-none items-center gap-2">
            <Button variant="secondary" size="sm" :disabled="!canCancel" @click="openCancelModal">Cancel</Button>
            <Button v-if="canDelete" variant="danger" size="sm" :loading="deleting" @click="remove">Delete</Button>
          </div>
        </div>
      </section>

      <div class="grid flex-none grid-cols-1 gap-4 lg:min-h-0 lg:flex-1 xl:grid-cols-[minmax(0,1fr)_minmax(360px,.48fr)]">
        <div class="grid min-h-fit grid-cols-1 gap-4 lg:min-h-0 lg:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,.95fr)]">
          <div class="flex min-h-fit flex-col gap-4 lg:min-h-0 xl:grid xl:grid-rows-[auto_minmax(260px,1fr)]">
          <Card icon="briefcase" title="Case & assignment" subtitle="The operational facts at a glance">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-3 text-[13px] min-[420px]:grid-cols-2">
              <div><dt class="eyebrow mb-1">Assigned Surveyor</dt><dd class="font-medium text-ink">{{ item.assignee_name || 'No one yet' }}</dd></div>
              <div><dt class="eyebrow mb-1">Assignment Status</dt><dd><Badge v-if="assignment" :variant="assignment.variant">{{ assignment.label }}</Badge></dd></div>
              <div><dt class="eyebrow mb-1">Assigned At</dt><dd class="tabular text-ink-soft">{{ dateTimeLabel(item.assigned_at) }}</dd></div>
              <div><dt class="eyebrow mb-1">Planned Inspection</dt><dd class="tabular text-ink-soft">{{ dateTimeLabel(item.planned_at) }}</dd></div>
            </dl>
            <div class="mt-3 border-t border-hairline pt-3"><p class="eyebrow mb-1">Field notes</p><p class="line-clamp-2 text-[13px] leading-5 text-ink-soft">{{ item.notes || 'No field notes were added.' }}</p></div>
          </Card>

          <Card icon="map-pin" title="Property location" :subtitle="`${item.lat.toFixed(5)}, ${item.lng.toFixed(5)}`" flush>
            <div class="h-64 overflow-hidden rounded-b-lg xl:h-full xl:min-h-[240px]"><LocationPicker :lat="item.lat" :lng="item.lng" readonly /></div>
          </Card>
          </div>

          <Card class="min-h-[420px] lg:min-h-0" icon="history" title="Status timeline" subtitle="Every lifecycle change, in order" scroll>
            <EmptyState v-if="!item.status_events?.length" icon="history" message="No status activity yet." />
            <ol v-else class="relative space-y-0">
              <li v-for="(event, index) in [...item.status_events].reverse()" :key="event.id" class="relative flex gap-3 pb-5 last:pb-0">
                <span v-if="index < item.status_events.length - 1" class="absolute bottom-0 left-[5px] top-3 w-px bg-hairline" />
                <span class="relative z-10 mt-1.5 h-[11px] w-[11px] flex-none rounded-full ring-4 ring-surface" :class="eventTone(event)" />
                <div class="min-w-0"><p class="text-[13.5px] font-semibold text-ink">{{ eventTitle(event) }}</p><p class="mt-0.5 text-[12.5px] leading-5 text-ink-soft">{{ event.note || 'Status updated.' }}</p><p class="mt-1 text-[11.5px] text-ink-faint">{{ event.actor_name || 'System' }} · <span class="tabular">{{ dateTimeLabel(event.created_at) }}</span></p></div>
              </li>
            </ol>
          </Card>

          <Card class="min-h-[440px] lg:col-span-2 xl:min-h-[480px]" icon="camera" title="Site photos" :subtitle="`${item.photos?.length ?? 0} uploaded`">
          <template #actions>
            <Badge :variant="item.photos?.some(photo => photo.is_gps_verified) ? 'success' : 'warning'">
              {{ item.photos?.some(photo => photo.is_gps_verified) ? 'Verified' : 'Needs verified photo' }}
            </Badge>
          </template>
          <EmptyState v-if="!item.photos?.length" icon="camera" message="No site photos have been uploaded yet." />
          <div v-else class="grid grid-cols-1 gap-3 min-[420px]:grid-cols-2 xl:grid-cols-3">
            <a
              v-for="photo in item.photos"
              :key="photo.id"
              :href="photo.url"
              target="_blank"
              rel="noreferrer"
              class="group overflow-hidden rounded-md border border-hairline bg-surface-sunken transition-colors hover:border-primary/40"
            >
              <div class="aspect-[4/3] overflow-hidden bg-surface">
                <img :src="photo.url" :alt="`Site photo captured ${shortDateTimeLabel(photo.captured_at)}`" class="h-full w-full object-cover transition-transform duration-fast group-hover:scale-[1.02]" loading="lazy" />
              </div>
              <div class="flex items-start justify-between gap-2 p-3">
                <div class="min-w-0">
                  <p class="truncate text-[12.5px] font-semibold text-ink">{{ shortDateTimeLabel(photo.captured_at) }}</p>
                  <p class="mt-0.5 truncate text-[11.5px] text-ink-faint">{{ photoDistanceLabel(photo.distance_from_case_m) }}</p>
                </div>
                <span class="grid h-7 w-7 flex-none place-items-center rounded-full" :class="photo.is_gps_verified ? 'bg-state-success-soft text-state-success' : 'bg-state-danger-soft text-state-danger'">
                  <Icon :name="photo.is_gps_verified ? 'check-circle' : 'alert-triangle'" class="h-4 w-4" />
                </span>
              </div>
            </a>
          </div>
        </Card>
        </div>

        <Card class="min-h-[560px] xl:min-h-0" icon="users" :title="canAssign ? (item.status === 'rejected' ? 'Reassign surveyor' : 'Assign surveyor') : 'Assignment'" subtitle="Location, availability and workload in one decision" flush>
          <template #actions><Badge v-if="canAssign" variant="success">{{ surveyorChoices.length }} available</Badge><Badge v-else :variant="assignment?.variant">{{ assignment?.label }}</Badge></template>
          <div v-if="canAssign" class="flex h-full min-h-0 flex-col">
            <InlineAlert v-if="item.status === 'rejected'" class="m-4 mb-0">{{ item.assignee_name || 'The previous surveyor' }} rejected this assignment. Select a replacement.</InlineAlert>
            <InlineAlert v-if="candidatesError" class="m-4 mb-0">{{ candidatesError }} You can still assign by workload.</InlineAlert>
            <div v-if="candidates.length" class="h-52 flex-none border-b border-hairline">
              <CaseAssignmentMap
                :case-lat="item.lat"
                :case-lng="item.lng"
                :candidates="candidates"
                :selected-id="selectedSurveyorId"
                @select="selectedSurveyorId = $event"
                @assign="assignFromMap"
              />
            </div>
            <div class="border-b border-hairline px-4 py-3"><div class="grid grid-cols-[1fr_auto_auto] gap-3 text-[10.5px] font-semibold uppercase tracking-wider text-ink-faint"><span>Surveyor</span><span>Cases</span><span>Workload</span></div></div>
            <div class="min-h-0 flex-1 overflow-y-auto">
              <div v-if="candidatesLoading && !surveyorChoices.length" class="space-y-2 p-4"><Skeleton v-for="i in 4" :key="i" class="h-20" rounded="md" /></div>
              <EmptyState v-else-if="!surveyorChoices.length" icon="users" message="No active surveyor is available for assignment." />
              <label v-for="choice in surveyorChoices" v-else :key="choice.employee.id" class="group grid cursor-pointer grid-cols-[minmax(0,1fr)_42px_76px] items-center gap-3 border-b border-hairline px-4 py-3 transition-colors last:border-0 hover:bg-surface-sunken" :class="selectedSurveyorId === choice.employee.id ? 'bg-primary-soft' : ''">
                <input v-model="selectedSurveyorId" type="radio" name="surveyor" class="sr-only" :value="choice.employee.id" />
                <div class="flex min-w-0 items-center gap-2.5"><Avatar :name="choice.employee.name" size="sm" /><div class="min-w-0"><p class="truncate text-[13px] font-semibold text-ink">{{ choice.employee.name }}</p><p class="mt-0.5 flex items-center gap-1.5 text-[11.5px] text-ink-faint"><span class="h-1.5 w-1.5 rounded-full" :class="choice.nearby?.connection_status === 'online' ? 'bg-state-success' : 'bg-state-neutral'" /><template v-if="choice.nearby">{{ formatDistance(choice.nearby.distance_m) }} away · {{ choice.nearby.connection_status }}</template><template v-else>Off shift / no live location</template></p><p class="mt-1 text-[11px] text-ink-soft">{{ choice.workload?.summary.pending ?? 0 }} pending · {{ choice.workload?.summary.scheduled ?? 0 }} scheduled<span v-if="choice.workload?.summary.overdue" class="text-state-danger"> · {{ choice.workload.summary.overdue }} overdue</span></p></div></div>
                <span class="tabular text-center text-[13px] font-semibold text-ink">{{ choice.activeCases }}</span>
                <div><div class="h-1.5 overflow-hidden rounded-full bg-surface-sunken"><span class="block h-full rounded-full" :class="choice.workloadPercent >= 80 ? 'bg-state-danger' : choice.workloadPercent >= 55 ? 'bg-state-warning' : 'bg-state-success'" :style="{ width: `${choice.workloadPercent}%` }" /></div><p class="mt-1 text-right text-[10.5px] tabular text-ink-faint">{{ choice.workloadPercent }}%</p></div>
              </label>
            </div>
            <div class="mt-auto border-t border-hairline bg-surface px-4 py-3"><Button class="w-full" :disabled="!selectedSurveyorId" :loading="assigning" @click="assignSelected">{{ assigning ? 'Assigning…' : selectedChoice ? `Assign case to ${selectedChoice.employee.name}` : 'Select a surveyor to assign' }}</Button><p class="mt-2 text-center text-[11px] text-ink-faint">The surveyor receives a notification and must accept before scheduling.</p></div>
          </div>
          <div v-else class="flex h-full min-h-[300px] flex-col p-5">
            <div class="flex items-center gap-3 rounded-md bg-surface-sunken p-4"><Avatar :name="item.assignee_name || 'Unassigned'" size="lg" :muted="!item.assignee_name" /><div class="min-w-0"><p class="font-semibold text-ink">{{ item.assignee_name || 'No surveyor assigned' }}</p><p class="mt-0.5 text-[12.5px] text-ink-soft">{{ assignment?.label }}</p></div></div>
            <dl class="mt-5 space-y-4 text-[13px]"><div class="flex items-center justify-between gap-3 border-b border-hairline pb-3"><dt class="text-ink-soft">Assigned at</dt><dd class="tabular text-right font-medium">{{ dateTimeLabel(item.assigned_at) }}</dd></div><div class="flex items-center justify-between gap-3 border-b border-hairline pb-3"><dt class="text-ink-soft">Accepted at</dt><dd class="tabular text-right font-medium">{{ dateTimeLabel(item.accepted_at) }}</dd></div><div class="flex items-center justify-between gap-3"><dt class="text-ink-soft">Inspection plan</dt><dd class="tabular text-right font-medium">{{ dateTimeLabel(item.planned_at) }}</dd></div></dl>
            <InlineAlert v-if="assignment?.status === 'awaiting_acceptance'" variant="warning" class="mt-5">Waiting for the surveyor to accept and set the inspection date and time.</InlineAlert>
          </div>
        </Card>
      </div>
    </div>

    <Modal v-model="cancelModalOpen" title="Cancel case">
      <form class="space-y-3.5" @submit.prevent="submitCancel"><p class="text-[13.5px] text-ink-soft">Cancelling stops this inspection and notifies the assigned surveyor and management.</p><div><label for="cancel-note" class="mb-1.5 block text-[12px] font-medium text-ink-soft">Reason (optional)</label><textarea id="cancel-note" v-model="cancelNote" rows="3" placeholder="e.g. Client withdrew the inspection request" class="field h-auto py-2.5" /></div></form>
      <template #footer><Button variant="secondary" @click="cancelModalOpen = false">Keep case</Button><Button variant="danger" :loading="cancelling" @click="submitCancel">Confirm cancellation</Button></template>
    </Modal>
  </AppShell>
</template>
