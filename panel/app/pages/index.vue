<script setup lang="ts">
// The sidebar's nav is Map / Shift templates / Employees — no "Home"
// destination exists in this design, so `/` just lands on the map, the
// natural default view for an ops console.
definePageMeta({ redirect: '/map' })
</script>

<template>
  <div />
</template>
