<script setup lang="ts">

const state = useConfirmState()
const { t } = useI18n()

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
      <Button variant="secondary" @click="respond(false)">{{ t('common.cancel') }}</Button>
      <Button class="w-full sm:w-auto" :variant="state.variant === 'danger' ? 'danger' : 'primary'" @click="respond(true)">{{ t('common.confirm') }}</Button>
    </template>
  </Modal>
</template>
