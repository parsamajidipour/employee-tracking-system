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
  <div class="surface-flat overflow-x-auto">
    <table class="w-full text-left text-[13px]">
      <thead>
        <tr class="border-b border-hairline">
          <th
            v-for="header in headers"
            :key="header"
            class="overline whitespace-nowrap px-4 py-2.5 text-left font-semibold"
          >
            {{ header }}
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-hairline">
        <tr v-if="loading">
          <td :colspan="headers.length" class="p-0">
            <div class="row-h flex items-center gap-4 px-4">
              <Skeleton v-for="i in headers.length" :key="i" class="h-3 flex-1" />
            </div>
          </td>
        </tr>
        <tr v-else-if="error">
          <td :colspan="headers.length" class="px-4 py-10">
            <EmptyState icon="alert-triangle" :message="error" tone="danger" />
          </td>
        </tr>
        <tr v-else-if="isEmpty">
          <td :colspan="headers.length" class="px-4 py-10">
            <EmptyState icon="inbox" :message="emptyMessage" />
          </td>
        </tr>
        <slot v-else />
      </tbody>
    </table>
  </div>
</template>
