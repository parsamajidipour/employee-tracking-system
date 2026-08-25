<script setup lang="ts">
withDefaults(
  defineProps<{
    headers: string[]
    loading?: boolean
    error?: string | null
    isEmpty?: boolean
    emptyMessage?: string
    embedded?: boolean
    skeletonRows?: number
  }>(),
  { emptyMessage: 'Nothing here yet.', embedded: false, skeletonRows: 6 },
)
</script>

<template>
  <div v-if="loading" :class="embedded ? 'divide-y divide-hairline' : 'surface-flat divide-y divide-hairline'">
    <div v-for="row in skeletonRows" :key="row" class="row-h flex items-center gap-4 px-4 sm:px-5">
      <Skeleton v-for="i in headers.length" :key="i" class="h-3 flex-1" />
    </div>
  </div>
  <div v-else-if="error" :class="embedded ? 'px-5 py-12' : 'surface-flat px-5 py-12'">
    <EmptyState icon="alert-triangle" :message="error" tone="danger" />
  </div>
  <div v-else-if="isEmpty" :class="embedded ? 'px-5 py-12' : 'surface-flat px-5 py-12'">
    <EmptyState icon="inbox" :message="emptyMessage">
      <template v-if="$slots.empty" #action><slot name="empty" /></template>
    </EmptyState>
  </div>
  <template v-else>
    <div class="grid grid-cols-1 gap-3 xl:hidden" :class="embedded ? 'p-3 sm:p-4' : ''">
      <slot name="cards" />
    </div>
    <div class="hidden overflow-x-auto xl:block" :class="embedded ? '' : 'surface-flat'">
      <table class="w-full text-left text-[14px]">
        <thead class="sticky top-0 z-10 bg-surface-sunken">
          <tr class="border-b border-hairline">
            <th
              v-for="header in headers"
              :key="header"
              class="eyebrow whitespace-nowrap px-4 py-2.5 text-left font-semibold sm:px-5"
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
