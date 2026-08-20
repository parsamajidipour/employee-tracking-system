<script setup lang="ts">
withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false })

const checked = defineModel<boolean>({ default: false })

function toggle() {
  checked.value = !checked.value
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === ' ' || e.key === 'Enter') {
    e.preventDefault()
    toggle()
  }
}
</script>

<template>
  <span
    role="switch"
    :aria-checked="checked"
    :aria-disabled="disabled"
    :tabindex="disabled ? -1 : 0"
    class="relative inline-block h-6 w-11 flex-none cursor-pointer rounded-pill transition-colors duration-fast ease-soft"
    :class="[checked ? 'bg-primary' : 'bg-hairline', disabled ? 'cursor-not-allowed opacity-50' : '']"
    @click="!disabled && toggle()"
    @keydown="!disabled && onKeydown($event)"
  >
    <span
      class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-key transition-transform duration-fast ease-soft"
      :class="checked ? 'translate-x-[22px]' : 'translate-x-0.5'"
    />
  </span>
</template>
