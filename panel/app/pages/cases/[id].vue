<script setup lang="ts">
import type { InspectionCase, NearestSurveyor } from '~/composables/useCases'
import { caseStatusLabel, caseStatusVariant, casePriorityLabel, casePriorityVariant } from '~/utils/caseStatus'
import { formatDistance } from '~/utils/formatDistance'

const route = useRoute()
const caseId = Number(route.params.id)
const toast = useToast()
const { confirm } = useConfirm()

const { data: employeesData, load: loadEmployees } = useEmployees()
const employees = computed(() => employeesData.value ?? [])

const item = ref<InspectionCase | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

const nearest = ref<NearestSurveyor[]>([])
const nearestLoading = ref(false)
const nearestError = ref<string | null>(null)

const manualAssignee = ref<number | ''>('')
const assigning = ref(false)

const cancelModalOpen = ref(false)
const cancelNote = ref('')
const cancelling = ref(false)

const deleting = ref(false)

const canAssign = computed(() => item.value?.status === 'pending')
const canCancel = computed(() => item.value ? ['pending', 'accepted', 'in_progress'].includes(item.value.status) : false)
const canDelete = computed(() => item.value?.status === 'pending')

async function load() {
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

async function loadNearest() {
  nearestLoading.value = true
  try {
    nearest.value = await fetchNearestSurveyors(caseId)
    nearestError.value = null
  } catch {
    nearestError.value = 'Could not load nearby surveyors.'
  } finally {
    nearestLoading.value = false
  }
}

async function assignTo(employeeId: number) {
  assigning.value = true
  try {
    await assignCase(caseId, employeeId)
    toast.success('Case assigned.')
    manualAssignee.value = ''
    await load()
  } catch (err) {
    toast.error(apiErrorMessage(err, 'This case can no longer be reassigned.'))
  } finally {
    assigning.value = false
  }
}

function assignManually() {
  if (!manualAssignee.value) return
  assignTo(manualAssignee.value)
}

function openCancelModal() {
  cancelNote.value = ''
  cancelModalOpen.value = true
}

async function submitCancel() {
  cancelling.value = true
  try {
    await cancelCase(caseId, cancelNote.value || undefined)
    toast.success('Case cancelled.')
    cancelModalOpen.value = false
    await load()
  } catch (err) {
    toast.error(apiErrorMessage(err, 'This case can no longer be cancelled.'))
  } finally {
    cancelling.value = false
  }
}

async function remove() {
  const confirmed = await confirm(`Delete case "${item.value?.title}"? This cannot be undone.`, {
    title: 'Delete case',
    variant: 'danger',
  })
  if (!confirmed) return

  deleting.value = true
  try {
    await deleteCase(caseId)
    toast.success('Case deleted.')
    await navigateTo('/cases')
  } catch (err) {
    toast.error(apiErrorMessage(err, 'Delete failed.'))
    deleting.value = false
  }
}

function timeLabel(value: string | null): string {
  return value ? new Date(value).toLocaleString() : '—'
}

onMounted(() => {
  loadEmployees()
  load()
  loadNearest()
})
</script>

<template>
  <AppShell :title="item?.title ?? 'Case'" :subtitle="item?.reference_no" back-to="/cases">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" @click="load(); loadNearest()">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
    </template>

    <InlineAlert v-if="error">{{ error }}</InlineAlert>

    <div v-if="loading && !item" class="space-y-3">
      <Skeleton class="h-16" rounded="md" />
      <Skeleton class="h-40" rounded="md" />
    </div>

    <template v-else-if="item">
      <div class="mb-5 grid grid-cols-1 gap-3.5 lg:grid-cols-[1fr_auto]">
        <div class="surface-flat space-y-3 p-5">
          <div class="flex flex-wrap items-center gap-2">
            <Badge :variant="caseStatusVariant(item.status)">{{ caseStatusLabel(item.status) }}</Badge>
            <Badge :variant="casePriorityVariant(item.priority)">{{ casePriorityLabel(item.priority) }} priority</Badge>
          </div>
          <p v-if="item.property_address" class="text-[13.5px] text-ink-soft">{{ item.property_address }}</p>
          <p class="tabular text-[12.5px] text-ink-faint">{{ item.lat.toFixed(6) }}, {{ item.lng.toFixed(6) }}</p>
          <p v-if="item.notes" class="text-[13.5px] text-ink">{{ item.notes }}</p>

          <dl class="grid grid-cols-2 gap-x-4 gap-y-2.5 border-t border-hairline pt-3 text-[13px] sm:grid-cols-4">
            <div>
              <dt class="eyebrow mb-1">Assignee</dt>
              <dd class="text-ink">{{ item.assignee_name ?? 'Unassigned' }}</dd>
            </div>
            <div>
              <dt class="eyebrow mb-1">Assigned</dt>
              <dd class="tabular text-ink-soft">{{ timeLabel(item.assigned_at) }}</dd>
            </div>
            <div>
              <dt class="eyebrow mb-1">Accepted</dt>
              <dd class="tabular text-ink-soft">{{ timeLabel(item.accepted_at) }}</dd>
            </div>
            <div>
              <dt class="eyebrow mb-1">Completed</dt>
              <dd class="tabular text-ink-soft">{{ timeLabel(item.completed_at) }}</dd>
            </div>
          </dl>
        </div>

        <div class="flex flex-none flex-row gap-2 lg:flex-col">
          <Button variant="danger" :disabled="!canCancel" @click="openCancelModal">Cancel case</Button>
          <Button v-if="canDelete" variant="secondary" :loading="deleting" @click="remove">Delete</Button>
        </div>
      </div>

      <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-2">
        <section class="surface-flat p-5">
          <h2 class="mb-3.5">Status timeline</h2>
          <EmptyState v-if="!item.status_events || item.status_events.length === 0" message="No status changes yet." />
          <ol v-else class="space-y-3.5">
            <li v-for="event in item.status_events" :key="event.id" class="flex items-start gap-3 border-b border-hairline pb-3.5 last:border-0 last:pb-0">
              <span class="mt-1 h-2 w-2 flex-none rounded-full bg-primary"></span>
              <div class="min-w-0">
                <p class="text-[13.5px] text-ink">
                  <span class="font-medium">{{ event.actor_name ?? 'System' }}</span>
                  moved this from
                  <Badge v-if="event.from_status" :variant="caseStatusVariant(event.from_status)">{{ caseStatusLabel(event.from_status) }}</Badge>
                  <span v-else class="text-ink-faint">nothing</span>
                  to
                  <Badge :variant="caseStatusVariant(event.to_status)">{{ caseStatusLabel(event.to_status) }}</Badge>
                </p>
                <p v-if="event.note" class="mt-1 text-[13px] text-ink-soft">{{ event.note }}</p>
                <p class="mt-1 text-[12px] tabular text-ink-faint">{{ timeLabel(event.created_at) }}</p>
              </div>
            </li>
          </ol>
        </section>

        <section class="surface-flat p-5">
          <h2 class="mb-3.5">Assign</h2>

          <template v-if="canAssign">
            <InlineAlert v-if="nearestError" class="mb-3">{{ nearestError }}</InlineAlert>

            <div v-if="nearestLoading" class="space-y-2">
              <Skeleton class="h-12" rounded="md" />
              <Skeleton class="h-12" rounded="md" />
            </div>

            <EmptyState v-else-if="nearest.length === 0" message="No surveyors currently on shift and tracked." />

            <ul v-else class="mb-4 space-y-2">
              <li
                v-for="surveyor in nearest"
                :key="surveyor.employee_id"
                class="flex items-center justify-between gap-3 rounded-md border border-hairline px-3.5 py-2.5"
              >
                <div class="min-w-0">
                  <p class="flex items-center gap-1.5 text-[13.5px] font-medium text-ink">
                    {{ surveyor.name }}
                    <Badge :variant="surveyor.connection_status === 'online' ? 'success' : 'neutral'">{{ surveyor.connection_status }}</Badge>
                  </p>
                  <p class="tabular text-[12px] text-ink-faint">{{ formatDistance(surveyor.distance_m) }} away — {{ surveyor.open_case_count }} open case{{ surveyor.open_case_count === 1 ? '' : 's' }}</p>
                </div>
                <Button size="sm" :loading="assigning" @click="assignTo(surveyor.employee_id)">Assign</Button>
              </li>
            </ul>

            <div class="flex items-end gap-2 border-t border-hairline pt-3.5">
              <div class="flex-1">
                <Select v-model="manualAssignee" label="Or pick any employee">
                  <option value="">Select employee</option>
                  <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                </Select>
              </div>
              <Button variant="secondary" :disabled="!manualAssignee" :loading="assigning" @click="assignManually">Assign</Button>
            </div>
          </template>
          <EmptyState v-else :message="`Case is ${caseStatusLabel(item.status).toLowerCase()} — assignment is only available while pending.`" />
        </section>
      </div>

      <section class="surface-flat mt-5 p-5">
        <h2 class="mb-3.5">Photos</h2>
        <EmptyState v-if="!item.photos || item.photos.length === 0" icon="camera" message="No photos uploaded yet." />
        <div v-else class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-4">
          <div v-for="photo in item.photos" :key="photo.id" class="overflow-hidden rounded-md border border-hairline">
            <img :src="photo.url" :alt="`Case photo ${photo.id}`" class="h-40 w-full object-cover" />
            <div class="space-y-1 p-2.5">
              <Badge v-if="photo.is_gps_verified" variant="success">GPS verified</Badge>
              <Badge v-else variant="warning">
                Not GPS-verified{{ photo.distance_from_case_m !== null ? ` — ${formatDistance(photo.distance_from_case_m)} from site` : '' }}
              </Badge>
              <p class="tabular text-[12px] text-ink-faint">{{ timeLabel(photo.captured_at) }}</p>
            </div>
          </div>
        </div>
      </section>
    </template>

    <Modal v-model="cancelModalOpen" title="Cancel case">
      <form class="space-y-3.5" @submit.prevent="submitCancel">
        <p class="text-[13.5px] text-ink-soft">This will mark the case cancelled. You can add an optional note.</p>
        <div>
          <label class="mb-1.5 block text-[12px] font-medium text-ink-soft">Note (optional)</label>
          <textarea v-model="cancelNote" rows="3" class="field h-auto py-2.5" />
        </div>
      </form>
      <template #footer>
        <Button variant="secondary" @click="cancelModalOpen = false">Keep case</Button>
        <Button variant="danger" :loading="cancelling" @click="submitCancel">{{ cancelling ? 'Cancelling…' : 'Confirm cancel' }}</Button>
      </template>
    </Modal>
  </AppShell>
</template>
