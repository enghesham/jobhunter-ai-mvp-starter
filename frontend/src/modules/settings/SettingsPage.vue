<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Workspace"
      title="Settings"
      description="Choose the settings area you want to manage. Each area is separated to keep the workflow clear."
    />

    <div class="grid gap-5 lg:grid-cols-2">
      <RouterLink
        v-for="item in settingAreas"
        :key="item.to"
        :to="item.to"
        class="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/60 transition hover:-translate-y-1 hover:border-sky-200 hover:shadow-lg hover:shadow-sky-100"
      >
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl" :class="item.iconClass">
              <i :class="['pi text-lg', item.icon]" />
            </div>
            <h2 class="mt-5 text-2xl font-semibold text-slate-950">{{ item.title }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">{{ item.description }}</p>
          </div>
          <i class="pi pi-arrow-up-right text-slate-300 transition group-hover:text-sky-500" />
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
          <Tag v-for="tag in item.tags" :key="tag" :value="tag" severity="secondary" />
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import Tag from 'primevue/tag'

import PageHeader from '@/shared/components/PageHeader.vue'

const settingAreas = [
  {
    title: 'Opportunity Controls',
    description: 'Tune relevance thresholds, match thresholds, and whether weak jobs are stored or shown.',
    to: '/settings/opportunity-controls',
    icon: 'pi-sliders-h',
    iconClass: 'bg-sky-100 text-sky-700',
    tags: ['Thresholds', 'Weak jobs', 'Best matches'],
  },
  {
    title: 'Answer Templates',
    description: 'Manage reusable templates used by apply packages, cover letters, and common application answers.',
    to: '/settings/answer-templates',
    icon: 'pi-file-edit',
    iconClass: 'bg-emerald-100 text-emerald-700',
    tags: ['Cover letter', 'Answers', 'Fallback content'],
  },
]
</script>
