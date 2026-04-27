<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Overview"
      title="Dashboard"
      description="A quick operational snapshot of sources, jobs, and pipeline progress. Missing endpoints degrade to safe defaults."
    />

    <ErrorState v-if="errorMessage" title="Dashboard unavailable" :message="errorMessage">
      <template #actions>
        <Button label="Retry" icon="pi pi-refresh" @click="loadDashboard" />
      </template>
    </ErrorState>

    <div v-if="loading" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
      <article v-for="index in 5" :key="index" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
        <Skeleton height="1rem" width="55%" class="rounded-lg" />
        <Skeleton height="2.2rem" width="35%" class="mt-4 rounded-lg" />
      </article>
    </div>

    <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
      <article
        v-for="stat in stats"
        :key="stat.label"
        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60"
      >
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-sm font-medium text-slate-500">{{ stat.label }}</p>
            <p class="mt-4 text-3xl font-semibold text-slate-950">{{ stat.value }}</p>
          </div>
          <div :class="stat.iconClass">
            <i :class="['pi text-lg', stat.icon]" />
          </div>
        </div>
      </article>
    </div>

    <PageCard
      eyebrow="Notes"
      title="Current Dashboard Behavior"
      description="Counts come from available list endpoints. When a dedicated total is not exposed, the UI falls back to the currently available list data or zero."
    >
      <div class="grid gap-3 text-sm text-slate-600 md:grid-cols-2">
        <div class="rounded-2xl bg-slate-50 p-4">Analyzed jobs are counted from the loaded jobs list where `analysis` exists or status is `analyzed`/`matched`.</div>
        <div class="rounded-2xl bg-slate-50 p-4">Matched jobs are counted from the loaded jobs list where status is `matched`.</div>
      </div>
    </PageCard>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'

import api from '@/app/services/api'
import { listJobSources } from '@/modules/job-sources/services/jobSourcesApi'
import { listJobs } from '@/modules/jobs/services/jobsApi'
import ErrorState from '@/shared/components/ErrorState.vue'
import PageCard from '@/shared/components/PageCard.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
import { extractCollection, getApiErrorMessage, getCollectionTotal } from '@/shared/utils/api'
import type { Job } from '@/modules/jobs/types'

interface DashboardCounts {
  totalSources: number
  totalJobs: number
  analyzedJobs: number
  matchedJobs: number
  applications: number
}

const loading = ref(false)
const errorMessage = ref('')
const counts = ref<DashboardCounts>({
  totalSources: 0,
  totalJobs: 0,
  analyzedJobs: 0,
  matchedJobs: 0,
  applications: 0,
})

const stats = computed(() => [
  { label: 'Total Job Sources', value: counts.value.totalSources, icon: 'pi-database', iconClass: 'rounded-2xl bg-sky-100 p-3 text-sky-700' },
  { label: 'Total Jobs', value: counts.value.totalJobs, icon: 'pi-briefcase', iconClass: 'rounded-2xl bg-emerald-100 p-3 text-emerald-700' },
  { label: 'Analyzed Jobs', value: counts.value.analyzedJobs, icon: 'pi-chart-line', iconClass: 'rounded-2xl bg-amber-100 p-3 text-amber-700' },
  { label: 'Matched Jobs', value: counts.value.matchedJobs, icon: 'pi-star', iconClass: 'rounded-2xl bg-violet-100 p-3 text-violet-700' },
  { label: 'Applications', value: counts.value.applications, icon: 'pi-send', iconClass: 'rounded-2xl bg-rose-100 p-3 text-rose-700' },
])

onMounted(async () => {
  await loadDashboard()
})

async function loadDashboard(): Promise<void> {
  loading.value = true
  errorMessage.value = ''

  try {
    const [sourcesCollection, jobsCollection, applicationsCount] = await Promise.all([
      listJobSources(),
      listJobs(),
      fetchApplicationsCount(),
    ])

    counts.value = {
      totalSources: getCollectionTotal(sourcesCollection),
      totalJobs: getCollectionTotal(jobsCollection),
      analyzedJobs: countAnalyzedJobs(jobsCollection.items),
      matchedJobs: countMatchedJobs(jobsCollection.items),
      applications: applicationsCount,
    }
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error, 'Failed to load dashboard data.')
  } finally {
    loading.value = false
  }
}

async function fetchApplicationsCount(): Promise<number> {
  try {
    const response = await api.get('/jobhunter/applications')
    const collection = extractCollection<unknown>(response.data)
    return getCollectionTotal(collection)
  } catch {
    return 0
  }
}

function countAnalyzedJobs(jobs: Job[]): number {
  return jobs.filter((job) => job.analysis || job.status === 'analyzed' || job.status === 'matched').length
}

function countMatchedJobs(jobs: Job[]): number {
  return jobs.filter((job) => job.status === 'matched').length
}
</script>
