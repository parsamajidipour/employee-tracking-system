<script setup lang="ts">
interface AppRelease {
  id: number
  version_code: number
  version_name: string
  release_notes: string | null
  is_mandatory: boolean
  file_size: number
  download_url: string
  created_at: string
}

const releases = ref<AppRelease[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const uploading = ref(false)
const uploadError = ref<string | null>(null)

const { confirm } = useConfirm()
const toast = useToast()

const apkFile = ref<File | null>(null)
const apkInput = ref<HTMLInputElement | null>(null)
const form = reactive({
  version_code: '',
  version_name: '',
  release_notes: '',
  is_mandatory: false,
})

function fileSize(bytes: number): string {
  if (bytes === 0) return '—'
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function onFileChange(event: Event) {
  apkFile.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

function resetForm() {
  form.version_code = ''
  form.version_name = ''
  form.release_notes = ''
  form.is_mandatory = false
  apkFile.value = null
  if (apkInput.value) apkInput.value.value = ''
}

async function load() {
  loading.value = true
  try {
    releases.value = await apiFetch<AppRelease[]>('/api/v1/app-releases')
    error.value = null
  } catch {
    error.value = 'Could not load releases. Sign in and try again.'
  } finally {
    loading.value = false
  }
}

async function upload() {
  if (!apkFile.value) {
    uploadError.value = 'Choose an .apk file to upload.'
    return
  }

  uploading.value = true
  uploadError.value = null
  try {
    const body = new FormData()
    body.append('apk', apkFile.value)
    body.append('version_code', form.version_code)
    body.append('version_name', form.version_name)
    if (form.release_notes) body.append('release_notes', form.release_notes)
    body.append('is_mandatory', form.is_mandatory ? '1' : '0')

    await apiFetch('/api/v1/app-releases', { method: 'POST', body })
    toast.success('Release uploaded.')
    resetForm()
    await load()
  } catch {
    uploadError.value = 'Upload failed — check the version code is unique and the file is a valid .apk.'
  } finally {
    uploading.value = false
  }
}

async function remove(release: AppRelease) {
  if (!(await confirm(`Retract version ${release.version_name} (${release.version_code})? Devices already updated keep it — this only stops it being offered.`, {
    variant: 'danger',
    title: 'Retract release',
  }))) return
  try {
    await apiFetch(`/api/v1/app-releases/${release.id}`, { method: 'DELETE' })
    toast.success('Release retracted.')
    await load()
  } catch {
    toast.error('Could not retract this release.')
  }
}

onMounted(load)
</script>

<template>
  <AppShell title="App releases" subtitle="Upload a new build for the Android update-check flow">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" @click="load">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        Refresh
      </Button>
    </template>

    <form class="surface-flat mb-5 space-y-4 p-4" @submit.prevent="upload">
      <h2>New release</h2>
      <InlineAlert v-if="uploadError">{{ uploadError }}</InlineAlert>

      <div>
        <span class="mb-1.5 block text-[12px] font-medium text-ink-soft">APK file</span>
        <input
          ref="apkInput"
          type="file"
          accept=".apk"
          class="field cursor-pointer file:mr-3 file:cursor-pointer file:rounded-sm file:border-0 file:bg-primary-soft file:px-2.5 file:py-1.5 file:text-[12.5px] file:font-semibold file:text-primary-strong"
          @change="onFileChange"
        />
      </div>

      <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-3">
        <TextInput v-model="form.version_code" type="number" min="1" label="Version code" hint="Matches versionCode / pubspec +build" required />
        <TextInput v-model="form.version_name" label="Version name" placeholder="1.1.0" hint="Semantic, e.g. 1.1.0" required />
        <label class="flex h-control cursor-pointer items-center gap-2 self-end rounded-md border border-hairline px-3 text-[12.5px] font-medium transition-colors hover:bg-surface-sunken">
          <input v-model="form.is_mandatory" type="checkbox" class="h-4 w-4 accent-primary" />
          Mandatory update
        </label>
      </div>

      <div>
        <span class="mb-1.5 block text-[12px] font-medium text-ink-soft">Release notes (optional)</span>
        <textarea v-model="form.release_notes" rows="3" class="field h-auto resize-none py-2.5" placeholder="What changed in this build" />
      </div>

      <Button type="submit" :disabled="uploading">
        <Icon name="upload" class="h-3.5 w-3.5" />
        {{ uploading ? 'Uploading…' : 'Publish release' }}
      </Button>
    </form>

    <Table
      :headers="['Version', 'Notes', 'Size', 'Type', 'Published', '']"
      :loading="loading"
      :error="error"
      :is-empty="releases.length === 0"
      empty-message="No releases published yet — upload the first build above."
    >
      <tr v-for="release in releases" :key="release.id" class="row-h text-ink">
        <td class="px-4">
          <div class="font-semibold tabular text-[13px]">v{{ release.version_name }}</div>
          <div class="text-[11px] text-ink-faint tabular">code {{ release.version_code }}</div>
        </td>
        <td class="max-w-64 truncate px-4 text-[13px] text-ink-soft">{{ release.release_notes ?? '—' }}</td>
        <td class="px-4 text-[13px] tabular">{{ fileSize(release.file_size) }}</td>
        <td class="px-4">
          <Badge :variant="release.is_mandatory ? 'danger' : 'neutral'">
            {{ release.is_mandatory ? 'Mandatory' : 'Optional' }}
          </Badge>
        </td>
        <td class="px-4 text-[13px] tabular">{{ new Date(release.created_at).toLocaleDateString() }}</td>
        <td class="px-4">
          <div class="flex items-center justify-end gap-0.5 whitespace-nowrap">
            <a :href="release.download_url" class="rounded-sm px-2 py-1.5 text-[12.5px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
              Download
            </a>
            <button type="button" class="rounded-sm px-2 py-1.5 text-[12.5px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" @click="remove(release)">
              Retract
            </button>
          </div>
        </td>
      </tr>
    </Table>
  </AppShell>
</template>
