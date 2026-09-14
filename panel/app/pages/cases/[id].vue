<script setup lang="ts">
import type { CaseStatusEvent, InspectionCase, NearestSurveyor } from '~/composables/useCases'
import { caseAssignmentDisplay, casePriorityVariant } from '~/utils/caseStatus'

const { t } = useI18n()
const { number, dateTime, distance: formatDistance } = useLocalizedFormat()

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
const selectedSurveyorIds = ref<number[]>([])
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
const selectedChoices = computed(() => surveyorChoices.value.filter(row => selectedSurveyorIds.value.includes(row.employee.id)))
const workflowSteps = computed(() => {
  const current = assignment.value?.status ?? 'unassigned'
  const order = ['unassigned', 'awaiting_acceptance', 'scheduled', 'in_progress', 'completed']
  const currentIndex = order.indexOf(current)
  return [
    { key: 'unassigned', label: t('cases.detail.steps.received'), compactLabel: t('cases.detail.steps.new') },
    { key: 'awaiting_acceptance', label: t('cases.detail.steps.assigned'), compactLabel: t('cases.detail.steps.assigned') },
    { key: 'scheduled', label: t('cases.detail.steps.scheduled'), compactLabel: t('cases.detail.steps.plan') },
    { key: 'in_progress', label: t('cases.detail.steps.inspection'), compactLabel: t('cases.detail.steps.inspect') },
    { key: 'completed', label: t('cases.detail.steps.completed'), compactLabel: t('cases.detail.steps.done') },
  ].map((step, index) => ({ ...step, done: current === 'completed' || (currentIndex >= 0 && index < currentIndex), active: step.key === current }))
})

async function loadCase() {
  loading.value = true
  try {
    const loaded = await fetchCase(caseId)
    item.value = loaded
    selectedSurveyorIds.value = (loaded.offered_to ?? []).map(offer => offer.employee_id)
    error.value = null
  } catch {
    error.value = t('cases.detail.loadFailed')
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
    candidatesError.value = t('cases.detail.rankingUnavailable')
  } finally {
    candidatesLoading.value = false
  }
}

async function refreshAll() {
  await Promise.all([loadCase(), loadCandidates(), loadEmployees(), loadWorkload()])
}

async function assignSelected() {
  if (!selectedSurveyorIds.value.length) {
    toast.error(t('cases.detail.selectFirst'))
    return
  }
  assigning.value = true
  try {
    await assignCase(caseId, selectedSurveyorIds.value)
    toast.success(t('cases.detail.assignedNotice', { count: selectedSurveyorIds.value.length }))
    await refreshAll()
  } catch (err) {
    toast.error(apiErrorMessage(err, t('cases.detail.assignFailed')))
    await refreshAll()
  } finally {
    assigning.value = false
  }
}

function toggleSurveyor(employeeId: number) {
  selectedSurveyorIds.value = selectedSurveyorIds.value.includes(employeeId)
    ? selectedSurveyorIds.value.filter(id => id !== employeeId)
    : [...selectedSurveyorIds.value, employeeId]
}

function openCancelModal() {
  cancelNote.value = ''
  cancelModalOpen.value = true
}

async function submitCancel() {
  cancelling.value = true
  try {
    await cancelCase(caseId, cancelNote.value || undefined)
    toast.success(t('cases.detail.cancelledNotice'))
    cancelModalOpen.value = false
    await refreshAll()
  } catch (err) {
    toast.error(apiErrorMessage(err, t('cases.detail.cancelFailed')))
  } finally {
    cancelling.value = false
  }
}

async function remove() {
  const confirmed = await confirm(
    t('cases.detail.deleteConfirm', { name: item.value?.title ?? '' }),
    { title: t('cases.detail.deleteTitle'), variant: 'danger' },
  )
  if (!confirmed) return
  deleting.value = true
  try {
    await deleteCase(caseId)
    toast.success(t('cases.detail.deleted'))
    await navigateTo('/cases')
  } catch (err) {
    toast.error(apiErrorMessage(err, t('cases.detail.deleteFailed')))
    deleting.value = false
  }
}

function dateTimeLabel(value: string | null): string {
  return value ? dateTime(value) : t('cases.detail.notSetYet')
}

function shortDateTimeLabel(value: string | null): string {
  return value ? dateTime(value, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : t('cases.detail.notSet')
}

function photoDistanceLabel(distance: number | null): string {
  if (distance === null) return t('cases.detail.distanceUnavailable')
  return t('cases.detail.fromProperty', { distance: formatDistance(distance) })
}

function eventTitle(event: CaseStatusEvent): string {
  if (event.to_status === 'pending' && /assigned|offered/i.test(event.note ?? '')) return t('cases.detail.events.assigned')
  if (!event.from_status && event.to_status === 'pending') return t('cases.detail.events.received')
  if (event.to_status === 'accepted') return t('cases.detail.events.accepted')
  if (event.to_status === 'in_progress') return t('cases.detail.events.started')
  if (event.to_status === 'overdue') return t('cases.detail.events.overdue')
  if (event.to_status === 'completed') return t('cases.detail.events.completed')
  if (event.to_status === 'rejected') return t('cases.detail.events.rejected')
  if (event.to_status === 'cancelled') return t('cases.detail.events.cancelled')
  return t('cases.detail.events.updated')
}

function eventNote(event: CaseStatusEvent): string {
  const note = event.note?.trim()
  if (!note) return t('cases.detail.events.statusUpdated')

  const fixedNotes: Record<string, string> = {
    'Case created.': 'createdNote',
    'Accepted by surveyor.': 'acceptedNote',
    'Rejected by surveyor.': 'rejectedNote',
    'Inspection started.': 'startedNote',
    'Planned inspection time passed.': 'overdueNote',
    'Inspection completed.': 'completedNote',
    'Cancelled by management.': 'cancelledNote',
  }
  const fixedKey = fixedNotes[note]
  if (fixedKey) return t(`cases.detail.events.${fixedKey}`)

  const reassigned = note.match(/^Reassigned from (.+) to (.+)\.$/)
  if (reassigned) return t('cases.detail.events.reassignedNote', { from: reassigned[1], to: reassigned[2] })

  const assigned = note.match(/^Assigned to (.+)\.$/)
  if (assigned) return t('cases.detail.events.assignedNote', { name: assigned[1] })

  const offered = note.match(/^Offered to (.+)\.$/)
  if (offered) return t('cases.detail.events.offeredNote', { names: offered[1] })

  return note
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
    toast.error(t('cases.detail.deletedElsewhere'))
    navigateTo('/cases')
    return
  }
  refreshAll()
})

onMounted(refreshAll)
</script>

<template>
  <AppShell :title="item?.title ?? t('cases.detail.workspace')" :subtitle="item?.reference_no" back-to="/cases" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" :aria-label="t('cases.detail.refresh')" @click="refreshAll">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">{{ t('common.refresh') }}</span>
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
                <Badge v-if="assignment" :variant="assignment.variant">{{ t(`case.assignmentStatuses.${assignment.status}`) }}</Badge>
                <Badge :variant="casePriorityVariant(item.priority)">{{ t('cases.detail.priority', { priority: t(`case.priorities.${item.priority}`) }) }}</Badge>
              </div>
              <p class="mt-1 truncate text-[13px] text-ink-soft">{{ item.property_address || t('cases.detail.addressMissing') }}</p>
            </div>
          </div>

          <ol class="grid min-w-0 flex-1 grid-cols-5 xl:max-w-2xl">
            <li v-for="(step, index) in workflowSteps" :key="step.key" class="relative flex min-w-0 flex-col items-center">
              <span v-if="index" class="absolute end-1/2 top-3 h-px w-full" :class="step.done || step.active ? 'bg-primary' : 'bg-hairline'" />
              <span class="relative z-10 grid h-6 w-6 place-items-center rounded-full border-2 text-[10px] font-bold" :class="step.done ? 'border-primary bg-primary text-white' : step.active ? 'border-primary bg-primary-soft text-primary-strong' : 'border-hairline bg-surface text-ink-faint'">
                <Icon v-if="step.done" name="check" class="h-3 w-3" /><span v-else>{{ number(index + 1) }}</span>
              </span>
              <span class="mt-1.5 max-w-full truncate px-0.5 text-[9.5px] font-medium min-[420px]:text-[10.5px]" :class="step.active || step.done ? 'text-ink' : 'text-ink-faint'">
                <span class="min-[420px]:hidden">{{ step.compactLabel }}</span>
                <span class="hidden min-[420px]:inline">{{ step.label }}</span>
              </span>
            </li>
          </ol>

          <div class="flex flex-none items-center gap-2">
            <Button variant="secondary" size="sm" :disabled="!canCancel" @click="openCancelModal">{{ t('common.cancel') }}</Button>
            <Button v-if="canDelete" variant="danger" size="sm" :loading="deleting" @click="remove">{{ t('common.delete') }}</Button>
          </div>
        </div>
      </section>

      <div class="grid flex-none grid-cols-1 gap-4 lg:min-h-0 lg:flex-1 xl:grid-cols-[minmax(0,1.05fr)_minmax(340px,.9fr)_minmax(360px,.95fr)]">
        <div class="flex min-h-fit flex-col gap-5 lg:min-h-0">
          <Card class="relative z-0 overflow-hidden" icon="map-pin" :title="t('cases.fields.locationOnMap')" :subtitle="`${number(item.lat, { maximumFractionDigits: 5 })}, ${number(item.lng, { maximumFractionDigits: 5 })}`" flush>
            <div class="relative isolate h-72 overflow-hidden rounded-b-md xl:h-[300px]"><LocationPicker :lat="item.lat" :lng="item.lng" readonly /></div>
          </Card>

          <Card class="relative z-10 min-h-[360px] xl:flex-1 xl:max-h-[calc(100vh-620px)] xl:min-h-[360px]" icon="camera" :title="t('cases.detail.sitePhotos')" :subtitle="t('cases.detail.uploaded', { count: number(item.photos?.length ?? 0) })" scroll>
            <template #actions>
              <Badge :variant="item.photos?.some(photo => photo.is_gps_verified) ? 'success' : 'warning'">
                {{ item.photos?.some(photo => photo.is_gps_verified) ? t('cases.detail.verified') : t('cases.detail.needsVerified') }}
              </Badge>
            </template>
            <EmptyState v-if="!item.photos?.length" icon="camera" :message="t('cases.detail.noPhotos')" />
            <div v-else class="grid grid-cols-1 gap-3 min-[420px]:grid-cols-2 xl:grid-cols-3">
              <a
                v-for="photo in item.photos"
                :key="photo.id"
                :href="photo.url"
                target="_blank"
                rel="noreferrer"
                class="group overflow-hidden rounded-md border border-hairline bg-surface-sunken transition-colors hover:border-primary/40"
              >
                <div class="aspect-[4/3] overflow-hidden bg-surface xl:aspect-square">
                  <img :src="photo.url" :alt="t('cases.detail.photoAlt', { date: shortDateTimeLabel(photo.captured_at) })" class="h-full w-full object-cover" loading="lazy" />
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

        <Card class="min-h-[420px] lg:min-h-0" icon="history" :title="t('cases.detail.timeline')" :subtitle="t('cases.detail.timelineSubtitle')" scroll>
          <EmptyState v-if="!item.status_events?.length" icon="history" :message="t('cases.detail.noActivity')" />
          <ol v-else class="relative space-y-0">
            <li v-for="(event, index) in [...item.status_events].reverse()" :key="event.id" class="relative flex gap-3 pb-5 last:pb-0">
              <span v-if="index < item.status_events.length - 1" class="absolute bottom-0 start-[5px] top-3 w-px bg-hairline" />
              <span class="relative z-10 mt-1.5 h-[11px] w-[11px] flex-none rounded-full ring-4 ring-surface" :class="eventTone(event)" />
              <div class="min-w-0"><p class="text-[13.5px] font-semibold text-ink">{{ eventTitle(event) }}</p><p class="mt-0.5 text-[12.5px] leading-5 text-ink-soft">{{ eventNote(event) }}</p><p class="mt-1 text-[11.5px] text-ink-faint">{{ event.actor_name || t('cases.detail.events.system') }} · <span class="tabular">{{ dateTimeLabel(event.created_at) }}</span></p></div>
            </li>
          </ol>
        </Card>

        <Card class="min-h-[560px] xl:min-h-0" icon="users" :title="canAssign ? (item.status === 'rejected' ? t('cases.detail.reassign') : t('cases.detail.assign')) : t('cases.detail.assignment')" :subtitle="t('cases.detail.assignmentSubtitle')" flush>
          <template #actions><Badge v-if="canAssign" variant="success">{{ t('cases.detail.available', { count: number(surveyorChoices.length) }) }}</Badge><Badge v-else :variant="assignment?.variant">{{ assignment ? t(`case.assignmentStatuses.${assignment.status}`) : '' }}</Badge></template>
          <div v-if="canAssign" class="flex h-full min-h-0 flex-col">
            <InlineAlert v-if="item.status === 'rejected'" class="m-4 mb-0">{{ t('cases.detail.previousRejected', { name: item.assignee_name || t('cases.detail.previousSurveyor') }) }}</InlineAlert>
            <InlineAlert v-if="candidatesError" class="m-4 mb-0">{{ candidatesError }} {{ t('cases.detail.assignByWorkload') }}</InlineAlert>
            <div v-if="candidates.length" class="h-52 flex-none border-b border-hairline">
              <CaseAssignmentMap
                :case-lat="item.lat"
                :case-lng="item.lng"
                :candidates="candidates"
                :selected-ids="selectedSurveyorIds"
                @select="toggleSurveyor"
              />
            </div>
            <div class="border-b border-hairline px-4 py-3"><div class="grid grid-cols-[1fr_auto_auto] gap-3 text-[10.5px] font-semibold uppercase tracking-wider text-ink-faint"><span>{{ t('cases.detail.surveyor') }}</span><span>{{ t('cases.detail.cases') }}</span><span>{{ t('cases.detail.workload') }}</span></div></div>
            <div class="min-h-0 flex-1 overflow-y-auto">
              <div v-if="candidatesLoading && !surveyorChoices.length" class="space-y-2 p-4"><Skeleton v-for="i in 4" :key="i" class="h-20" rounded="md" /></div>
              <EmptyState v-else-if="!surveyorChoices.length" icon="users" :message="t('cases.detail.noneAvailable')" />
              <label v-for="choice in surveyorChoices" v-else :key="choice.employee.id" class="group grid cursor-pointer grid-cols-[minmax(0,1fr)_42px_76px] items-center gap-3 border-b border-hairline px-4 py-3 transition-colors last:border-0 hover:bg-surface-sunken" :class="selectedSurveyorIds.includes(choice.employee.id) ? 'bg-primary-soft' : ''">
                <input v-model="selectedSurveyorIds" type="checkbox" name="surveyors" class="sr-only" :value="choice.employee.id" />
                <div class="flex min-w-0 items-center gap-2.5"><Avatar :name="choice.employee.name" size="sm" /><div class="min-w-0"><p class="truncate text-[13px] font-semibold text-ink">{{ choice.employee.name }}</p><p class="mt-0.5 flex items-center gap-1.5 text-[11.5px] text-ink-faint"><span class="h-1.5 w-1.5 rounded-full" :class="choice.nearby?.connection_status === 'online' ? 'bg-state-success' : 'bg-state-neutral'" /><template v-if="choice.nearby">{{ t('cases.assignmentMap.away', { distance: formatDistance(choice.nearby.distance_m) }) }} · {{ t(`employees.connection.${choice.nearby.connection_status}`) }}</template><template v-else>{{ t('cases.detail.offShift') }}</template></p><p class="mt-1 text-[11px] text-ink-soft">{{ t('cases.detail.pending', { count: number(choice.workload?.summary.pending ?? 0) }) }} · {{ t('cases.detail.scheduled', { count: number(choice.workload?.summary.scheduled ?? 0) }) }}<span v-if="choice.workload?.summary.overdue" class="text-state-danger"> · {{ t('cases.detail.overdue', { count: number(choice.workload.summary.overdue) }) }}</span></p></div></div>
                <span class="tabular text-center text-[13px] font-semibold text-ink">{{ number(choice.activeCases) }}</span>
                <div><div class="h-1.5 overflow-hidden rounded-full bg-surface-sunken"><span class="block h-full rounded-full" :class="choice.workloadPercent >= 80 ? 'bg-state-danger' : choice.workloadPercent >= 55 ? 'bg-state-warning' : 'bg-state-success'" :style="{ width: `${choice.workloadPercent}%` }" /></div><p class="mt-1 text-end text-[10.5px] tabular text-ink-faint">{{ number(choice.workloadPercent) }}%</p></div>
              </label>
            </div>
            <div class="mt-auto border-t border-hairline bg-surface px-4 py-3"><Button class="w-full" :disabled="!selectedSurveyorIds.length" :loading="assigning" @click="assignSelected">{{ assigning ? t('cases.detail.assigning') : selectedChoices.length ? t('cases.detail.offerTo', { count: number(selectedChoices.length) }) : t('cases.detail.selectToAssign') }}</Button><p class="mt-2 text-center text-[11px] text-ink-faint">{{ t('cases.detail.assignmentHint') }}</p></div>
          </div>
          <div v-else class="flex h-full min-h-[300px] flex-col p-5">
            <div class="flex items-center gap-3 rounded-md bg-surface-sunken p-4"><Avatar :name="item.assignee_name || t('cases.detail.unassigned')" size="lg" :muted="!item.assignee_name" /><div class="min-w-0"><p class="font-semibold text-ink">{{ item.assignee_name || t('cases.detail.noSurveyor') }}</p><p class="mt-0.5 text-[12.5px] text-ink-soft">{{ assignment ? t(`case.assignmentStatuses.${assignment.status}`) : '' }}</p></div></div>
            <dl class="mt-5 space-y-4 text-[13px]"><div class="flex items-center justify-between gap-3 border-b border-hairline pb-3"><dt class="text-ink-soft">{{ t('cases.detail.assignedAt') }}</dt><dd class="tabular text-end font-medium">{{ dateTimeLabel(item.assigned_at) }}</dd></div><div class="flex items-center justify-between gap-3 border-b border-hairline pb-3"><dt class="text-ink-soft">{{ t('cases.detail.acceptedAt') }}</dt><dd class="tabular text-end font-medium">{{ dateTimeLabel(item.accepted_at) }}</dd></div><div class="flex items-center justify-between gap-3"><dt class="text-ink-soft">{{ t('cases.detail.inspectionPlan') }}</dt><dd class="tabular text-end font-medium">{{ dateTimeLabel(item.planned_at) }}</dd></div></dl>
            <InlineAlert v-if="assignment?.status === 'awaiting_acceptance'" variant="warning" class="mt-5">{{ t('cases.detail.waitingAcceptance') }}</InlineAlert>
          </div>
        </Card>
      </div>
    </div>

    <Modal v-model="cancelModalOpen" :title="t('cases.detail.cancelCase')">
      <form class="space-y-3.5" @submit.prevent="submitCancel"><p class="text-[13.5px] text-ink-soft">{{ t('cases.detail.cancelExplanation') }}</p><div><label for="cancel-note" class="mb-1.5 block text-[12px] font-medium text-ink-soft">{{ t('cases.detail.reasonOptional') }}</label><textarea id="cancel-note" v-model="cancelNote" rows="3" :placeholder="t('cases.detail.reasonPlaceholder')" class="field h-auto py-2.5" /></div></form>
      <template #footer><Button variant="secondary" @click="cancelModalOpen = false">{{ t('cases.detail.keepCase') }}</Button><Button variant="danger" :loading="cancelling" @click="submitCancel">{{ t('cases.detail.confirmCancellation') }}</Button></template>
    </Modal>
  </AppShell>
</template>
