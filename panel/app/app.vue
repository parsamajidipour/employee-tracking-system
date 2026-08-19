<script setup lang="ts">
const route = useRoute()
const showChrome = computed(() => route.path !== '/login')
</script>

<template>
  <div>
    <NuxtRouteAnnouncer />

    <div v-if="showChrome" class="flex h-dvh overflow-hidden bg-canvas">
      <AppSidebar />
      <div class="flex min-w-0 flex-1 flex-col">
        <NuxtPage :transition="{ name: 'page', mode: 'out-in' }" />
      </div>
    </div>
    <NuxtPage v-else />

    <ConfirmDialog />
    <ToastContainer />
  </div>
</template>
