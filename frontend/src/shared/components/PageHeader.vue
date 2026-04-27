<template>
  <div class="mb-6">
    <p v-if="eyebrow" class="text-xs font-semibold uppercase tracking-[0.3em] text-sky-600">{{ eyebrow }}</p>
    <h1 class="mt-3 text-3xl font-semibold text-slate-900">{{ title }}</h1>
    <p v-if="description" class="mt-2 text-sm leading-6 text-slate-600">{{ description }}</p>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string
  description?: string
  eyebrow?: string
}>()
</script>
