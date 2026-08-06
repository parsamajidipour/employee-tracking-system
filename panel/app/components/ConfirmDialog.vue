<script setup lang="ts">
// Mounted once in app.vue. Every useConfirm().confirm() call anywhere in
// the app resolves through this single instance.
const state = useConfirmState()

function respond(value: boolean) {
  state.open = false
  state.resolve?.(value)
  state.resolve = null
}
</script>

<template>
  <Modal v-model="state.open" :title="state.title">
    <p>{{ state.message }}</p>
    <template #footer>
      <Button variant="secondary" @click="respond(false)">Cancel</Button>
      <Button :variant="state.variant === 'danger' ? 'danger' : 'primary'" @click="respond(true)">Confirm</Button>
    </template>
  </Modal>
</template>
