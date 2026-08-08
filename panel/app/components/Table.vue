<script setup lang="ts">

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
  <div class="card overflow-x-auto">
    <table class="w-full border-collapse text-left text-sm tabular">
      <thead>
        <tr class="border-b border-hairline bg-surface-muted">
          <th v-for="h in headers" :key="h" class="px-5 py-3 text-[11px] font-semibold uppercase tracking-[0.8px] text-ink-soft">
            {{ h }}
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-hairline">
        <tr v-if="loading">
          <td :colspan="headers.length" class="px-5 py-12 text-center text-sm text-ink-faint">Loading…</td>
        </tr>
        <tr v-else-if="error">
          <td :colspan="headers.length" class="px-5 py-12">
            <div class="flex items-center justify-center gap-2 text-sm text-state-danger">
              <span class="inline-block h-2 w-2 flex-none rounded-full bg-state-danger"></span>
              {{ error }}
            </div>
          </td>
        </tr>
        <tr v-else-if="isEmpty">
          <td :colspan="headers.length" class="px-5 py-12 text-center text-sm text-ink-faint">{{ emptyMessage }}</td>
        </tr>
        <slot v-else />
      </tbody>
    </table>
  </div>
</template>
