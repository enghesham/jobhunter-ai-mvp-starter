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
    case 'offer_received':
    case 'strong_match':
    case 'greenhouse':
      return 'success'
    case 'analyzed':
    case 'interviewing':
    case 'interview':
    case 'good_match':
    case 'lever':
    case 'follow_up_scheduled':
    case 'response_received':
      return 'info'
    case 'applied':
    case 'apply':
    case 'ready_to_apply':
    case 'weak_match':
    case 'custom':
    case 'applied_manually':
    case 'follow_up_sent':
      return 'warn'
    case 'consider':
      return 'info'
    case 'rejected':
    case 'inactive':
    case 'archived':
    case 'skip':
      return 'danger'
    case 'application_created':
    case 'resume_linked':
    case 'status_changed':
    case 'interview_scheduled':
    case 'note_added':
      return 'contrast'
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
