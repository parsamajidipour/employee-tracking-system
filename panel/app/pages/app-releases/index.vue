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
const apkInput = ref<{ reset: () => void } | null>(null)
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

function resetForm() {
  form.version_code = ''
  form.version_name = ''
  form.release_notes = ''
  form.is_mandatory = false
  apkInput.value?.reset()
}

async function load() {
  loading.value = true
  try {
    releases.value = await apiFetch<AppRelease[]>('/api/v1/app-releases')
    error.value = null
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load releases. Sign in and try again.')
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
  } catch (err) {
    uploadError.value = apiErrorMessage(err, 'Upload failed — check the version code is unique and the file is a valid .apk.')
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
  } catch (err) {
    toast.error(apiErrorMessage(err, 'Could not retract this release.'))
  }
}

onMounted(load)
</script>

<template>
  <AppShell title="App releases" subtitle="Upload a new build for the Android update-check flow">
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" @click="load">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
    </template>

    <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[380px_1fr]">
      <form class="surface-flat flex flex-col items-start space-y-4 p-5" @submit.prevent="upload">
        <h2>New release</h2>
        <InlineAlert v-if="uploadError">{{ uploadError }}</InlineAlert>

        <FileInput ref="apkInput" v-model="apkFile" accept=".apk" label="APK file" hint="Universal build, all ABIs" required class="w-full" />

        <TextInput v-model="form.version_code" type="number" min="1" label="Version code" hint="Matches versionCode / pubspec +build" required class="w-full" />
        <TextInput v-model="form.version_name" label="Version name" placeholder="1.1.0" hint="Semantic, e.g. 1.1.0" required class="w-full" />

        <label class="flex w-fit items-center gap-3 text-[13px] font-medium text-ink">
          <Toggle v-model="form.is_mandatory" />
          Mandatory update
        </label>

        <div class="w-full">
          <span class="mb-1.5 block text-[12.5px] font-medium text-ink-soft">Release notes (optional)</span>
          <textarea v-model="form.release_notes" rows="3" class="field h-auto resize-none py-2.5" placeholder="What changed in this build" />
        </div>

        <Button type="submit" :disabled="uploading" class="w-full justify-center">
          <Icon name="upload" class="h-3.5 w-3.5" />
          {{ uploading ? 'Uploading…' : 'Publish release' }}
        </Button>
      </form>

      <Table
        :headers="['Version', 'Notes', 'Size', 'Type', 'Published', '']"
        :loading="loading"
        :error="error"
        :is-empty="releases.length === 0"
        empty-message="No releases published yet — upload the first build."
      >
        <template #cards>
          <div v-for="release in releases" :key="release.id" class="surface-flat space-y-3 p-4">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-semibold tabular text-[14px] text-ink">v{{ release.version_name }}</p>
                <p class="text-[12px] text-ink-faint tabular">code {{ release.version_code }}</p>
              </div>
              <Badge :variant="release.is_mandatory ? 'danger' : 'neutral'">
                {{ release.is_mandatory ? 'Mandatory' : 'Optional' }}
              </Badge>
            </div>

            <p v-if="release.release_notes" class="text-[13px] text-ink-soft">{{ release.release_notes }}</p>

            <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-[13px]">
              <div>
                <dt class="eyebrow mb-1">Size</dt>
                <dd class="tabular text-ink">{{ fileSize(release.file_size) }}</dd>
              </div>
              <div>
                <dt class="eyebrow mb-1">Published</dt>
                <dd class="tabular text-ink">{{ new Date(release.created_at).toLocaleDateString() }}</dd>
              </div>
            </dl>

            <div class="flex items-center gap-1 border-t border-hairline pt-2.5">
              <a :href="release.download_url" class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
                Download
              </a>
              <button type="button" class="rounded-sm px-2.5 py-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" @click="remove(release)">
                Retract
              </button>
            </div>
          </div>
        </template>

        <tr v-for="release in releases" :key="release.id" class="row-h text-ink">
          <td class="px-5">
            <div class="font-semibold tabular text-[14px]">v{{ release.version_name }}</div>
            <div class="text-[12px] text-ink-faint tabular">code {{ release.version_code }}</div>
          </td>
          <td class="max-w-64 truncate px-5 text-[14px] text-ink-soft">{{ release.release_notes ?? '—' }}</td>
          <td class="px-5 text-[14px] tabular">{{ fileSize(release.file_size) }}</td>
          <td class="px-5">
            <Badge :variant="release.is_mandatory ? 'danger' : 'neutral'">
              {{ release.is_mandatory ? 'Mandatory' : 'Optional' }}
            </Badge>
          </td>
          <td class="px-5 text-[14px] tabular">{{ new Date(release.created_at).toLocaleDateString() }}</td>
          <td class="px-5">
            <div class="flex items-center justify-end gap-1 whitespace-nowrap">
              <a :href="release.download_url" class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
                Download
              </a>
              <button type="button" class="rounded-sm px-2.5 py-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" @click="remove(release)">
                Retract
              </button>
            </div>
          </td>
        </tr>
      </Table>
    </div>
  </AppShell>
</template>
