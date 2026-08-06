<script setup lang="ts">
// Bakes loading/error/empty states into the table itself so every page that
// uses it gets all three for free, consistently — see the design pass's
// "every page needs a loading state, an empty state, and an error state"
// requirement.
withDefaults(
  defineProps<{
    headers: string[]
    loading?: boolean
    error?: string | null
    isEmpty?: boolean
    emptyMessage?: string
  }>(),
  { loading: false, error: null, isEmpty: false, emptyMessage: 'Nothing here yet.' },
)
</script>

<template>
  <div class="overflow-x-auto rounded border border-slate-200 bg-white">
    <table class="w-full border-collapse text-left text-sm tabular-nums">
      <thead>
        <tr class="border-b border-slate-200 bg-slate-50">
          <th v-for="h in headers" :key="h" class="px-4 py-2.5 text-xs font-medium uppercase tracking-wide text-slate-500">
            {{ h }}
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <tr v-if="loading">
          <td :colspan="headers.length" class="px-4 py-10 text-center text-sm text-slate-400">Loading…</td>
        </tr>
        <tr v-else-if="error">
          <td :colspan="headers.length" class="px-4 py-10">
            <div class="flex items-center justify-center gap-2 text-sm text-red-700">
              <span class="inline-block h-1.5 w-1.5 flex-none rounded-full bg-red-600"></span>
              {{ error }}
            </div>
          </td>
        </tr>
        <tr v-else-if="isEmpty">
          <td :colspan="headers.length" class="px-4 py-10 text-center text-sm text-slate-400">{{ emptyMessage }}</td>
        </tr>
        <slot v-else />
      </tbody>
    </table>
  </div>
</template>
