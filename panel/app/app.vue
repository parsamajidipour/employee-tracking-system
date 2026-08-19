<template>
  <div>
    <NuxtRouteAnnouncer />
    <NuxtPage :transition="{ name: 'page', mode: 'out-in' }" />
    <ConfirmDialog />
    <ToastContainer />
  </div>
</template>
