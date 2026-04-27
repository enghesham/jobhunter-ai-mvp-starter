<template>
  <Button
    :label="loading ? loadingLabel : label"
    :icon="loading ? 'pi pi-spin pi-spinner' : icon"
    :disabled="disabled || loading"
    :loading="loading"
    v-bind="$attrs"
  />
</template>

<script setup lang="ts">
import Button from 'primevue/button'

defineOptions({
  inheritAttrs: false,
})

withDefaults(defineProps<{
  label: string
  loading?: boolean
  loadingLabel?: string
  icon?: string
  disabled?: boolean
}>(), {
  loading: false,
  loadingLabel: 'Please wait...',
  icon: undefined,
  disabled: false,
})
</script>
