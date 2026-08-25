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

const currentRelease = computed(() => releases.value[0] ?? null)

const totalSize = computed(() => releases.value.reduce((sum, release) => sum + release.file_size, 0))

function fileSize(bytes: number): string {
  if (bytes === 0) return '—'
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function nextVersionCode(): string {
  const highest = releases.value.reduce((max, release) => Math.max(max, release.version_code), 0)
  return String(highest + 1)
}

function resetForm() {
  form.version_code = nextVersionCode()
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
    form.version_code = nextVersionCode()
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
  if (form.version_name.trim() === '') {
    uploadError.value = 'Give the build a version name, e.g. 1.1.0.'
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
    toast.success('Release published. Every active employee has been notified.')
    resetForm()
    await load()
  } catch (err) {
    uploadError.value = apiErrorMessage(err, 'Upload failed — check the version code is unique and the file is a valid .apk.')
    toast.error(uploadError.value)
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
  <AppShell title="App releases" subtitle="Builds offered to the Android update-check flow" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" @click="load">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">Refresh</span>
      </Button>
    </template>

    <div class="grid h-full min-h-0 grid-cols-1 gap-4 overflow-y-auto p-4 sm:p-5 lg:grid-cols-[minmax(0,400px)_minmax(0,1fr)] lg:overflow-hidden">
      <div class="min-h-0 lg:overflow-y-auto lg:pr-1">
        <Card icon="upload" title="New release" subtitle="Publishing notifies every active employee">
          <form class="space-y-4" @submit.prevent="upload">
            <InlineAlert v-if="uploadError" class="!mb-0">{{ uploadError }}</InlineAlert>

            <FileInput
              ref="apkInput"
              v-model="apkFile"
              accept=".apk"
              label="APK file"
              hint="Universal build — arm64-v8a and armeabi-v7a"
              required
            />

            <div class="grid grid-cols-2 gap-3">
              <TextInput
                v-model="form.version_code"
                type="number"
                min="1"
                label="Version code"
                placeholder="e.g. 7"
                required
              />
              <TextInput v-model="form.version_name" label="Version name" placeholder="e.g. 1.1.0" required />
            </div>
            <p class="-mt-2 text-[11.5px] text-ink-faint">
              Version code must match <span class="tabular">versionCode</span> / pubspec <span class="tabular">+build</span> —
              it defaults to the next unused number.
            </p>

            <div class="flex items-center justify-between gap-3 rounded-md bg-surface-sunken px-3.5 py-3">
              <div class="min-w-0">
                <p class="text-[13px] font-medium text-ink">Mandatory update</p>
                <p class="text-[12px] text-ink-faint">Blocks the app until the employee installs it.</p>
              </div>
              <Toggle v-model="form.is_mandatory" />
            </div>

            <div>
              <label for="release-notes" class="mb-1.5 block text-[12px] font-medium text-ink-soft">Release notes (optional)</label>
              <textarea
                id="release-notes"
                v-model="form.release_notes"
                rows="3"
                class="field h-auto resize-none py-2.5"
                placeholder="What changed in this build"
              />
            </div>

            <Button type="submit" :loading="uploading" class="w-full justify-center">
              <Icon v-if="!uploading" name="upload" class="h-3.5 w-3.5" />
              {{ uploading ? 'Uploading…' : 'Publish release' }}
            </Button>
          </form>
        </Card>
      </div>

      <div class="flex min-h-0 flex-col gap-4">
        <div class="grid flex-none grid-cols-3 gap-2.5">
          <StatCard icon="download" label="Current version" :value="currentRelease ? `v${currentRelease.version_name}` : '—'" accent="primary" />
          <StatCard icon="inbox" label="Published builds" :value="String(releases.length)" accent="neutral" />
          <StatCard icon="upload" label="Storage used" :value="fileSize(totalSize)" accent="neutral" />
        </div>

        <Card class="min-h-0 flex-1" icon="download" title="Published releases" :subtitle="`${releases.length} available to devices`" flush>
          <div class="h-full min-h-0 overflow-y-auto">
            <Table
              embedded
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
                      <p class="tabular text-[14px] font-semibold text-ink">v{{ release.version_name }}</p>
                      <p class="tabular text-[12px] text-ink-faint">code {{ release.version_code }}</p>
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

              <tr
                v-for="(release, index) in releases"
                :key="release.id"
                class="group row-h text-ink transition-colors hover:bg-surface-sunken/60"
              >
                <td class="px-4 sm:px-5">
                  <div class="flex items-center gap-2">
                    <span class="tabular text-[14px] font-semibold">v{{ release.version_name }}</span>
                    <Badge v-if="index === 0" variant="success">Current</Badge>
                  </div>
                  <div class="tabular text-[12px] text-ink-faint">code {{ release.version_code }}</div>
                </td>
                <td class="max-w-64 truncate px-4 text-[14px] text-ink-soft sm:px-5">{{ release.release_notes ?? '—' }}</td>
                <td class="px-4 text-[14px] tabular sm:px-5">{{ fileSize(release.file_size) }}</td>
                <td class="px-4 sm:px-5">
                  <Badge :variant="release.is_mandatory ? 'danger' : 'neutral'">
                    {{ release.is_mandatory ? 'Mandatory' : 'Optional' }}
                  </Badge>
                </td>
                <td class="px-4 text-[14px] tabular sm:px-5">{{ new Date(release.created_at).toLocaleDateString() }}</td>
                <td class="px-4 sm:px-5">
                  <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                    <a
                      :href="release.download_url"
                      class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong opacity-0 transition-opacity hover:bg-surface-sunken group-hover:opacity-100 focus-visible:opacity-100"
                    >
                      Download
                    </a>
                    <button
                      type="button"
                      class="rounded-sm px-2.5 py-2 text-[13px] text-ink-soft opacity-0 transition-opacity hover:bg-surface-sunken hover:text-state-danger group-hover:opacity-100 focus-visible:opacity-100"
                      @click="remove(release)"
                    >
                      Retract
                    </button>
                  </div>
                </td>
              </tr>
            </Table>
          </div>
        </Card>
      </div>
    </div>
  </AppShell>
</template>
