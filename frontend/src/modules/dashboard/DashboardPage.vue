<template>
  <PageCard
    eyebrow="Overview"
    title="Dashboard"
    description="This page will aggregate sources, jobs, matches, resumes, and applications once the frontend modules are implemented."
  >
    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded-2xl bg-slate-50 p-4">
        <p class="text-sm text-slate-500">Sources</p>
        <p class="mt-2 text-3xl font-semibold">0</p>
      </div>
      <div class="rounded-2xl bg-slate-50 p-4">
        <p class="text-sm text-slate-500">Jobs</p>
        <p class="mt-2 text-3xl font-semibold">0</p>
      </div>
      <div class="rounded-2xl bg-slate-50 p-4">
        <p class="text-sm text-slate-500">Applications</p>
        <p class="mt-2 text-3xl font-semibold">0</p>
      </div>
    </div>
  </PageCard>
</template>

<script setup lang="ts">
import PageCard from '@/shared/components/PageCard.vue'
</script>
