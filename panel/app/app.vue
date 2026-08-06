<template>
  <div>
    <NuxtRouteAnnouncer />
    <nav class="border-b border-slate-200 bg-white px-4 py-2 text-sm">
      <NuxtLink to="/" class="mr-4 text-slate-700 hover:underline">Home</NuxtLink>
      <NuxtLink to="/teams" class="mr-4 text-slate-700 hover:underline">Teams</NuxtLink>
      <NuxtLink to="/shift-templates" class="mr-4 text-slate-700 hover:underline">Shift templates</NuxtLink>
      <NuxtLink to="/employees" class="text-slate-700 hover:underline">Employees</NuxtLink>
    </nav>
    <NuxtPage />
  </div>
</template>
