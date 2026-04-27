<template>
  <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm shadow-sm shadow-slate-200/50">
    <span class="font-semibold text-slate-900">{{ normalizedScore }}%</span>
    <span class="text-slate-500">{{ label }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  score?: number | null
  label?: string
}>(), {
  label: 'Score',
})

const normalizedScore = computed(() => {
  if (typeof props.score !== 'number' || Number.isNaN(props.score)) {
    return 0
  }

  return props.score <= 1 ? Math.round(props.score * 100) : Math.round(props.score)
})
</script>
