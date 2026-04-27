<template>
  <small v-if="message" class="block text-sm text-red-600">
    {{ message }}
  </small>
</template>

<script setup lang="ts">
defineProps<{
  message?: string | null
}>()
</script>
