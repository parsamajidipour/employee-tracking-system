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

const { t, tm } = useI18n()
const { number, date, fileSize } = useLocalizedFormat()

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
    error.value = apiErrorMessage(err, t('releases.loadFailed'))
  } finally {
    loading.value = false
  }
}

async function upload() {
  if (!apkFile.value) {
    uploadError.value = t('releases.chooseApk')
    return
  }
  if (form.version_name.trim() === '') {
    uploadError.value = t('releases.nameRequired')
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
    toast.success(t('releases.publishedNotice'))
    resetForm()
    await load()
  } catch (err) {
    uploadError.value = apiErrorMessage(err, t('releases.uploadFailed'))
    toast.error(uploadError.value)
  } finally {
    uploading.value = false
  }
}

async function remove(release: AppRelease) {
  if (!(await confirm(t('releases.retractConfirm', { name: release.version_name, code: number(release.version_code) }), {
    variant: 'danger',
    title: t('releases.retractTitle'),
  }))) return
  try {
    await apiFetch(`/api/v1/app-releases/${release.id}`, { method: 'DELETE' })
    toast.success(t('releases.retracted'))
    await load()
  } catch (err) {
    toast.error(apiErrorMessage(err, t('releases.retractFailed')))
  }
}

onMounted(load)
</script>

<template>
  <AppShell :title="t('releases.title')" :subtitle="t('releases.subtitle')" full-bleed>
    <template #actions>
      <Button variant="secondary" size="sm" :disabled="loading" :aria-label="t('releases.refresh')" @click="load">
        <Icon name="refresh" class="h-3.5 w-3.5" :spin="loading" />
        <span class="hidden sm:inline">{{ t('common.refresh') }}</span>
      </Button>
    </template>

    <div class="flex h-full min-h-0 flex-col gap-3 overflow-y-auto p-3 sm:gap-4 sm:p-5 lg:grid lg:grid-cols-[minmax(0,400px)_minmax(0,1fr)] lg:overflow-hidden">
      <div class="flex-none lg:min-h-0 lg:overflow-y-auto lg:pe-1">
        <Card icon="upload" :title="t('releases.new')" :subtitle="t('releases.newSubtitle')">
          <form class="space-y-4" @submit.prevent="upload">
            <InlineAlert v-if="uploadError" class="!mb-0">{{ uploadError }}</InlineAlert>

            <FileInput
              ref="apkInput"
              v-model="apkFile"
              accept=".apk"
              :label="t('releases.apkFile')"
              :hint="t('releases.apkHint')"
              required
            />

            <div class="grid grid-cols-1 gap-3 min-[360px]:grid-cols-2">
              <TextInput
                v-model="form.version_code"
                type="number"
                min="1"
                :label="t('releases.versionCode')"
                :placeholder="t('releases.versionCodePlaceholder')"
                required
              />
              <TextInput v-model="form.version_name" :label="t('releases.versionName')" :placeholder="t('releases.versionNamePlaceholder')" required />
            </div>
            <p class="-mt-2 text-[11.5px] text-ink-faint">
              {{ t('releases.versionHint') }}
            </p>

            <div class="flex items-center justify-between gap-3 rounded-md bg-surface-sunken px-3.5 py-3">
              <div class="min-w-0">
                <p class="text-[13px] font-medium text-ink">{{ t('releases.mandatory') }}</p>
                <p class="text-[12px] text-ink-faint">{{ t('releases.mandatoryHint') }}</p>
              </div>
              <Toggle v-model="form.is_mandatory" />
            </div>

            <div>
              <label for="release-notes" class="mb-1.5 block text-[12px] font-medium text-ink-soft">{{ t('releases.notes') }}</label>
              <textarea
                id="release-notes"
                v-model="form.release_notes"
                rows="3"
                class="field h-auto resize-none py-2.5"
                :placeholder="t('releases.notesPlaceholder')"
              />
            </div>

            <Button type="submit" :loading="uploading" class="w-full justify-center">
              <Icon v-if="!uploading" name="upload" class="h-3.5 w-3.5" />
              {{ uploading ? t('releases.uploading') : t('releases.publish') }}
            </Button>
          </form>
        </Card>
      </div>

      <div class="flex flex-none flex-col gap-4 lg:min-h-0">
        <div class="grid flex-none grid-cols-1 gap-2.5 min-[480px]:grid-cols-3">
          <StatCard icon="download" :label="t('releases.currentVersion')" :value="currentRelease ? `v${currentRelease.version_name}` : '—'" accent="primary" />
          <StatCard icon="inbox" :label="t('releases.publishedBuilds')" :value="number(releases.length)" accent="neutral" />
          <StatCard icon="upload" :label="t('releases.storageUsed')" :value="totalSize ? fileSize(totalSize) : '—'" accent="neutral" />
        </div>

        <Card class="lg:min-h-0 lg:flex-1" icon="download" :title="t('releases.publishedReleases')" :subtitle="t('releases.available', { count: number(releases.length) })" flush>
          <div class="h-full min-h-0 overflow-y-auto">
            <Table
              embedded
              :headers="tm('releases.headers') as string[]"
              :loading="loading"
              :error="error"
              :is-empty="releases.length === 0"
              :empty-message="t('releases.empty')"
            >
              <template #cards>
                <div v-for="release in releases" :key="release.id" class="surface-flat space-y-3 p-3.5 sm:p-4">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="tabular text-[14px] font-semibold text-ink">v{{ release.version_name }}</p>
                      <p class="tabular text-[12px] text-ink-faint">{{ t('releases.versionCodeShort', { code: number(release.version_code) }) }}</p>
                    </div>
                    <Badge :variant="release.is_mandatory ? 'danger' : 'neutral'">
                      {{ release.is_mandatory ? t('releases.mandatoryShort') : t('releases.optionalShort') }}
                    </Badge>
                  </div>

                  <p v-if="release.release_notes" class="text-[13px] text-ink-soft">{{ release.release_notes }}</p>

                  <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-[13px]">
                    <div>
                      <dt class="eyebrow mb-1">{{ t('releases.size') }}</dt>
                      <dd class="tabular text-ink">{{ fileSize(release.file_size) }}</dd>
                    </div>
                    <div>
                      <dt class="eyebrow mb-1">{{ t('releases.published') }}</dt>
                      <dd class="tabular text-ink">{{ date(release.created_at) }}</dd>
                    </div>
                  </dl>

                  <div class="flex items-center gap-1 border-t border-hairline pt-2.5">
                    <a :href="release.download_url" class="inline-flex min-h-10 items-center rounded-sm px-3 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken">
                      {{ t('releases.download') }}
                    </a>
                    <button type="button" class="min-h-10 rounded-sm px-3 py-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger" @click="remove(release)">
                      {{ t('releases.retract') }}
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
                    <Badge v-if="index === 0" variant="success">{{ t('releases.current') }}</Badge>
                  </div>
                  <div class="tabular text-[12px] text-ink-faint">{{ t('releases.versionCodeShort', { code: number(release.version_code) }) }}</div>
                </td>
                <td class="max-w-64 truncate px-4 text-[14px] text-ink-soft sm:px-5">{{ release.release_notes ?? '—' }}</td>
                <td class="px-4 text-[14px] tabular sm:px-5">{{ fileSize(release.file_size) }}</td>
                <td class="px-4 sm:px-5">
                  <Badge :variant="release.is_mandatory ? 'danger' : 'neutral'">
                    {{ release.is_mandatory ? t('releases.mandatoryShort') : t('releases.optionalShort') }}
                  </Badge>
                </td>
                <td class="px-4 text-[14px] tabular sm:px-5">{{ date(release.created_at) }}</td>
                <td class="px-4 sm:px-5">
                  <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                    <a
                      :href="release.download_url"
                      class="rounded-sm px-2.5 py-2 text-[13px] font-medium text-primary-strong transition-colors hover:bg-surface-sunken"
                    >
                      {{ t('releases.download') }}
                    </a>
                    <button
                      type="button"
                      class="rounded-sm px-2.5 py-2 text-[13px] text-ink-soft transition-colors hover:bg-surface-sunken hover:text-state-danger"
                      @click="remove(release)"
                    >
                      {{ t('releases.retract') }}
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
