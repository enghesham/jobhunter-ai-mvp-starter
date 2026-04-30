<template>
  <Tag :severity="resolvedSeverity" :value="resolvedLabel" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Tag from 'primevue/tag'

const props = defineProps<{
  value?: string | null
  label?: string
}>()

const resolvedLabel = computed(() => props.label ?? formatLabel(props.value))
const resolvedSeverity = computed<'contrast' | 'info' | 'success' | 'warn' | 'danger'>(() => {
  switch (props.value) {
    case 'active':
    case 'matched':
    case 'offer':
    case 'strong_match':
    case 'greenhouse':
      return 'success'
    case 'analyzed':
    case 'interview':
    case 'good_match':
    case 'lever':
      return 'info'
    case 'applied':
    case 'apply':
    case 'ready_to_apply':
    case 'weak_match':
    case 'custom':
      return 'warn'
    case 'consider':
      return 'info'
    case 'rejected':
    case 'inactive':
    case 'archived':
    case 'skip':
      return 'danger'
    default:
      return 'contrast'
  }
})

function formatLabel(value?: string | null): string {
  if (!value) {
    return 'Unknown'
  }

  return value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}
</script>
