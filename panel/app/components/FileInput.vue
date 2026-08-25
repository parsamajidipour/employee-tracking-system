<script setup lang="ts">
withDefaults(
  defineProps<{
    accept?: string
    label?: string
    hint?: string
    required?: boolean
  }>(),
  {},
)

const file = defineModel<File | null>({ default: null })
const inputRef = ref<HTMLInputElement | null>(null)
const dragging = ref(false)

function pick() {
  inputRef.value?.click()
}

function onChange(e: Event) {
  file.value = (e.target as HTMLInputElement).files?.[0] ?? null
}

function onDrop(e: DragEvent) {
  dragging.value = false
  const dropped = e.dataTransfer?.files?.[0]
  if (!dropped) return

  file.value = dropped
  if (inputRef.value) {
    const transfer = new DataTransfer()
    transfer.items.add(dropped)
    inputRef.value.files = transfer.files
  }
}

function clear() {
  file.value = null
  if (inputRef.value) inputRef.value.value = ''
}

defineExpose({ reset: clear })

function formatSize(bytes: number): string {
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<template>
  <div>
    <label v-if="label" class="mb-1.5 block text-[12.5px] font-medium text-ink-soft">{{ label }}</label>

    <input ref="inputRef" type="file" :accept="accept" :required="required && !file" class="sr-only" @change="onChange" />

    <div
      v-if="!file"
      class="flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-md border border-dashed py-6 text-center transition-colors duration-fast"
      :class="dragging ? 'border-primary bg-primary-soft' : 'border-hairline bg-surface-sunken hover:border-primary/50'"
      @click="pick"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <Icon name="upload" class="h-5 w-5 text-ink-faint" />
      <p class="text-[12.5px] text-ink-soft"><span class="font-semibold text-primary-strong">Click to upload</span> or drag and drop</p>
      <p v-if="hint" class="text-[11px] text-ink-faint">{{ hint }}</p>
    </div>

    <div v-else class="flex h-control items-center gap-3 rounded-md border border-hairline bg-surface-sunken px-3.5">
      <Icon name="upload" class="h-4 w-4 flex-none text-primary-strong" />
      <div class="min-w-0 flex-1">
        <p class="truncate text-[13px] font-medium text-ink">{{ file.name }}</p>
        <p class="text-[11px] text-ink-faint">{{ formatSize(file.size) }}</p>
      </div>
      <button
        type="button"
        class="grid h-9 w-9 flex-none place-items-center rounded-sm text-ink-faint transition-colors hover:bg-surface hover:text-state-danger"
        aria-label="Remove file"
        @click="clear"
      >
        <Icon name="close" class="h-4 w-4" />
      </button>
    </div>
  </div>
</template>
