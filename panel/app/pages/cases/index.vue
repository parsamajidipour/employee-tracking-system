<script setup lang="ts">
import type { CaseStatus, NewCasePayload } from '~/composables/useCases'
import { CASE_PRIORITIES, CASE_STATUSES, caseStatusLabel, caseStatusVariant, casePriorityLabel, casePriorityVariant } from '~/utils/caseStatus'

const toast = useToast()

const { data: employeesData, load: loadEmployees } = useEmployees()
const employees = computed(() => employeesData.value ?? [])

const { data: cases, meta, loading, error, load } = useCasesList()

const statusFilter = ref<CaseStatus | ''>('')
const assigneeFilter = ref<number | ''>('')
const page = ref(1)

function currentFilters() {
  return {
    status: statusFilter.value || undefined,
    assigned_to: assigneeFilter.value || undefined,
    page: page.value,
  }
}

function refresh() {
  return load(currentFilters())
}

watch([statusFilter, assigneeFilter], () => {
  page.value = 1
  refresh()
})

function goToPage(next: number) {
  if (!meta.value || next < 1 || next > meta.value.last_page) return
  page.value = next
  refresh()
}

useCaseAssignmentAlerts(refresh)

const modalOpen = ref(false)
const submitting = ref(false)
const formError = ref<string | null>(null)

const form = reactive({
  reference_no: '',
  title: '',
  property_address: '',
  lat: '',
  lng: '',
  priority: 'normal' as (typeof CASE_PRIORITIES)[number],
  notes: '',
  assigned_to: '' as number | '',
})

function resetForm() {
  form.reference_no = ''
  form.title = ''
  form.property_address = ''
  form.lat = ''
  form.lng = ''
  form.priority = 'normal'
  form.notes = ''
  form.assigned_to = ''
  formError.value = null
}

function openModal() {
  resetForm()
  modalOpen.value = true
}

async function submit() {
  const lat = Number(form.lat)
  const lng = Number(form.lng)
  if (!form.reference_no || !form.title || Number.isNaN(lat) || Number.isNaN(lng)) {
    formError.value = 'Reference, title, and a valid latitude/longitude are required.'
    return
  }

  submitting.value = true
  formError.value = null
  try {
    const payload: NewCasePayload = {
      reference_no: form.reference_no,
      title: form.title,
      property_address: form.property_address || undefined,
      lat,
      lng,
      priority: form.priority,
      notes: form.notes || undefined,
      assigned_to: form.assigned_to || undefined,
    }
    await createCase(payload)
    toast.success('Case created.')
    modalOpen.value = false
    page.value = 1
    await refresh()
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Could not create case — check the fields.')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadEmployees()
  refresh()
})
</script>

<template>
  <AppShell title="Cases" :subtitle="meta ? `${meta.total} total` : undefined">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" @click="refresh">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
      <Button size="sm" @click="openModal">
        <Icon name="plus" class="h-3.5 w-3.5" />
        <span class="hidden sm:inline">New case</span>
      </Button>
    </template>

    <div class="surface-flat mb-4 flex flex-wrap items-end gap-3.5 p-4">
      <div class="w-48">
        <Select v-model="statusFilter" label="Status">
          <option value="">All statuses</option>
          <option v-for="status in CASE_STATUSES" :key="status" :value="status">{{ caseStatusLabel(status) }}</option>
        </Select>
      </div>
      <div class="w-56">
        <Select v-model="assigneeFilter" label="Assignee">
          <option value="">All employees</option>
          <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
        </Select>
      </div>
    </div>

    <Table
      :headers="['Reference', 'Title', 'Assignee', 'Status', 'Priority', 'Created', '']"
      :loading="loading"
      :error="error"
      :is-empty="cases.length === 0"
      empty-message="No cases match these filters."
    >
      <template #cards>
        <NuxtLink v-for="item in cases" :key="item.id" :to="`/cases/${item.id}`" class="surface-flat block space-y-3 p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate text-[14px] font-medium text-ink">{{ item.title }}</p>
              <p class="truncate text-[12px] text-ink-faint">{{ item.reference_no }}</p>
            </div>
            <Badge :variant="caseStatusVariant(item.status)">{{ caseStatusLabel(item.status) }}</Badge>
          </div>
          <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-[13px]">
            <div>
              <dt class="eyebrow mb-1">Assignee</dt>
              <dd class="text-ink">{{ item.assignee_name ?? 'Unassigned' }}</dd>
            </div>
            <div>
              <dt class="eyebrow mb-1">Priority</dt>
              <dd><Badge :variant="casePriorityVariant(item.priority)">{{ casePriorityLabel(item.priority) }}</Badge></dd>
            </div>
          </dl>
        </NuxtLink>
      </template>

      <tr v-for="item in cases" :key="item.id" class="row-h cursor-pointer text-ink hover:bg-surface-sunken/60" @click="navigateTo(`/cases/${item.id}`)">
        <td class="px-5 text-[14px] font-medium tabular">{{ item.reference_no }}</td>
        <td class="px-5 text-[14px]">{{ item.title }}</td>
        <td class="px-5 text-[14px] text-ink-soft">{{ item.assignee_name ?? 'Unassigned' }}</td>
        <td class="px-5"><Badge :variant="caseStatusVariant(item.status)">{{ caseStatusLabel(item.status) }}</Badge></td>
        <td class="px-5"><Badge :variant="casePriorityVariant(item.priority)">{{ casePriorityLabel(item.priority) }}</Badge></td>
        <td class="px-5 text-[13px] tabular text-ink-faint">{{ new Date(item.created_at).toLocaleDateString() }}</td>
        <td class="px-5 text-right">
          <NuxtLink :to="`/cases/${item.id}`" class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken" @click.stop>
            View
          </NuxtLink>
        </td>
      </tr>
    </Table>

    <div v-if="meta && meta.last_page > 1" class="mt-4 flex items-center justify-between">
      <p class="text-[12.5px] text-ink-faint">Page {{ meta.current_page }} of {{ meta.last_page }} — {{ meta.total }} cases</p>
      <div class="flex items-center gap-2">
        <Button variant="secondary" size="sm" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">Prev</Button>
        <Button variant="secondary" size="sm" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">Next</Button>
      </div>
    </div>

    <Modal v-model="modalOpen" title="New case">
      <form class="space-y-3.5" @submit.prevent="submit">
        <InlineAlert v-if="formError">{{ formError }}</InlineAlert>

        <TextInput v-model="form.reference_no" label="Reference no." required />
        <TextInput v-model="form.title" label="Title" required />
        <TextInput v-model="form.property_address" label="Property address" />

        <div class="grid grid-cols-2 gap-3.5">
          <TextInput v-model="form.lat" type="number" label="Latitude" required />
          <TextInput v-model="form.lng" type="number" label="Longitude" required />
        </div>

        <Select v-model="form.priority" label="Priority">
          <option v-for="priority in CASE_PRIORITIES" :key="priority" :value="priority">{{ casePriorityLabel(priority) }}</option>
        </Select>

        <Select v-model="form.assigned_to" label="Assign now (optional)">
          <option value="">Leave unassigned</option>
          <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
        </Select>

        <div>
          <label class="mb-1.5 block text-[12px] font-medium text-ink-soft">Notes</label>
          <textarea v-model="form.notes" rows="3" class="field h-auto py-2.5" />
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" @click="modalOpen = false">Cancel</Button>
        <Button :loading="submitting" @click="submit">{{ submitting ? 'Creating…' : 'Create case' }}</Button>
      </template>
    </Modal>
  </AppShell>
</template>
