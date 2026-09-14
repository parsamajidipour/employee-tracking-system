<script setup lang="ts">
const { $pwa } = useNuxtApp()
const { t } = useI18n()

const visible = computed(() => Boolean(
  $pwa?.showInstallPrompt
  || $pwa?.needRefresh
  || $pwa?.offlineReady,
))

const title = computed(() => {
  if ($pwa?.needRefresh) return t('pwa.updateTitle')
  if ($pwa?.showInstallPrompt) return t('pwa.installTitle')
  return t('pwa.offlineTitle')
})

const message = computed(() => {
  if ($pwa?.needRefresh) return t('pwa.updateMessage')
  if ($pwa?.showInstallPrompt) return t('pwa.installMessage')
  return t('pwa.offlineMessage')
})

async function accept() {
  if ($pwa?.needRefresh) {
    await $pwa.updateServiceWorker(true)
    return
  }
  if ($pwa?.showInstallPrompt) await $pwa.install()
}

async function close() {
  if ($pwa?.showInstallPrompt) {
    $pwa.cancelInstall()
    return
  }
  await $pwa?.cancelPrompt()
}
</script>

<template>
  <Transition enter-active-class="transition-opacity duration-base" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-fast" leave-to-class="opacity-0">
    <aside v-if="visible" class="pwa-prompt elevated-overlay bg-surface p-4" role="status">
      <div class="flex items-start gap-3">
        <span class="grid h-9 w-9 flex-none place-items-center rounded-sm bg-primary-soft text-primary-strong">
          <Icon :name="$pwa?.offlineReady ? 'check-circle' : 'download'" class="h-5 w-5" />
        </span>
        <div class="min-w-0 flex-1">
          <h2 class="text-[14px] font-semibold text-ink">{{ title }}</h2>
          <p class="mt-0.5 text-[12px] leading-5 text-ink-soft">{{ message }}</p>
        </div>
      </div>
      <div class="mt-3 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button variant="ghost" size="sm" class="w-full sm:w-auto" @click="close">{{ t('pwa.later') }}</Button>
        <Button v-if="$pwa?.needRefresh || $pwa?.showInstallPrompt" size="sm" class="w-full sm:w-auto" @click="accept">
          {{ $pwa?.needRefresh ? t('pwa.update') : t('pwa.install') }}
        </Button>
      </div>
    </aside>
  </Transition>
</template>
