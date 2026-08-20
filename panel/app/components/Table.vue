<script setup lang="ts">
withDefaults(
  defineProps<{
    headers: string[]
    loading?: boolean
    error?: string | null
    isEmpty?: boolean
    emptyMessage?: string
  }>(),
  { emptyMessage: 'Nothing here yet.' },
)
</script>

<template>
  <div v-if="loading" class="surface-flat">
    <div class="row-h flex items-center gap-4 px-5">
      <Skeleton v-for="i in headers.length" :key="i" class="h-3 flex-1" />
    </div>
  </div>
  <div v-else-if="error" class="surface-flat px-5 py-10">
    <EmptyState icon="alert-triangle" :message="error" tone="danger" />
  </div>
  <div v-else-if="isEmpty" class="surface-flat px-5 py-10">
    <EmptyState icon="inbox" :message="emptyMessage" />
  </div>
  <template v-else>
    <div class="grid grid-cols-1 gap-3 xl:hidden">
      <slot name="cards" />
    </div>
    <div class="surface-flat hidden overflow-x-auto xl:block">
      <table class="w-full text-left text-[14px]">
        <thead>
          <tr class="border-b border-hairline">
            <th
              v-for="header in headers"
              :key="header"
              class="eyebrow whitespace-nowrap px-5 py-3 text-left font-semibold"
            >
              {{ header }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-hairline">
          <slot />
        </tbody>
      </table>
    </div>
  </template>
</template>
