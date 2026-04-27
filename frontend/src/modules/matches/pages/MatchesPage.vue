<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Scoring"
      title="Matches"
      description="Review persisted match results created from the Jobs page, including score breakdowns and recommendation notes."
    />

    <ErrorState v-if="errorMessage" title="Matches unavailable" :message="errorMessage">
      <template #actions>
        <Button label="Retry" icon="pi pi-refresh" @click="loadMatches" />
      </template>
    </ErrorState>

    <SkeletonTable v-if="loading" :columns="6" />

    <div v-else class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <IconField class="w-full lg:max-w-sm">
          <InputIcon class="pi pi-search" />
          <InputText v-model.trim="query" fluid placeholder="Search by job, company, or candidate" />
        </IconField>

        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
          Showing {{ filteredMatches.length }} of {{ totalMatches }} matches
        </div>
      </div>

      <EmptyState
        v-if="filteredMatches.length === 0"
        title="No persisted matches yet"
        description="Run Match from the Jobs page and the results will appear here automatically."
        icon="pi-star"
      >
        <template #actions>
          <RouterLink to="/jobs">
            <Button label="Go to Jobs" icon="pi pi-briefcase" />
          </RouterLink>
        </template>
      </EmptyState>

      <DataTable
        v-else
        :value="filteredMatches"
        data-key="id"
        paginator
        :rows="10"
        class="mt-6"
        striped-rows
        responsive-layout="scroll"
      >
        <Column header="Job Title">
          <template #body="{ data }">
            <div>
              <p class="font-medium text-slate-900">{{ data.job?.title || `Job #${data.job_id}` }}</p>
              <p class="text-sm text-slate-500">{{ data.job?.company_name || 'Unknown company' }}</p>
            </div>
          </template>
        </Column>

        <Column header="Candidate">
          <template #body="{ data }">
            <div>
              <p class="font-medium text-slate-900">{{ data.candidate_profile?.full_name || `Profile #${data.profile_id}` }}</p>
              <p class="text-sm text-slate-500">{{ data.candidate_profile?.headline || 'Candidate profile' }}</p>
            </div>
          </template>
        </Column>

        <Column header="Overall Score">
          <template #body="{ data }">
            <ScoreBadge :score="data.overall_score" label="Overall" />
          </template>
        </Column>

        <Column header="Recommendation">
          <template #body="{ data }">
            <StatusTag :value="data.recommendation" />
          </template>
        </Column>

        <Column header="Matched At">
          <template #body="{ data }">
            {{ formatDateTime(data.matched_at || data.created_at) }}
          </template>
        </Column>

        <Column header="Actions" :style="{ width: '10rem' }">
          <template #body="{ data }">
            <Button label="View Details" icon="pi pi-eye" size="small" text @click="openDetails(data)" />
          </template>
        </Column>
      </DataTable>
    </div>

    <Dialog v-model:visible="detailsDialogVisible" modal header="Match Details" :style="{ width: '48rem' }">
      <div v-if="selectedMatch" class="space-y-5">
        <div class="grid gap-4 md:grid-cols-[9rem_minmax(0,1fr)] md:items-center">
          <div class="text-center">
            <Knob :model-value="normalizeScore(selectedMatch.overall_score)" :max="100" readonly value-template="{value}%" />
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-3">
              <h3 class="text-2xl font-semibold text-slate-900">{{ selectedMatch.job?.title || `Job #${selectedMatch.job_id}` }}</h3>
              <StatusTag :value="selectedMatch.recommendation" />
            </div>
            <p class="mt-1 text-sm text-slate-500">{{ selectedMatch.job?.company_name || 'Unknown company' }}</p>
            <p class="mt-2 text-sm leading-6 text-slate-700">{{ selectedMatch.notes || 'No additional notes were returned.' }}</p>
          </div>
        </div>

        <div class="space-y-4">
          <div v-for="metric in metrics" :key="metric.label">
            <div class="mb-2 flex items-center justify-between text-sm">
              <span class="font-medium text-slate-700">{{ metric.label }}</span>
              <span class="text-slate-500">{{ metric.value }}%</span>
            </div>
            <ProgressBar :value="metric.value" />
          </div>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Knob from 'primevue/knob'
import ProgressBar from 'primevue/progressbar'
import { RouterLink } from 'vue-router'

import type { JobMatch } from '@/modules/jobs/types'
import { listMatches } from '@/modules/matches/services/matchesApi'
import EmptyState from '@/shared/components/EmptyState.vue'
import ErrorState from '@/shared/components/ErrorState.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
import ScoreBadge from '@/shared/components/ScoreBadge.vue'
import SkeletonTable from '@/shared/components/SkeletonTable.vue'
import StatusTag from '@/shared/components/StatusTag.vue'
import { useDebouncedValue } from '@/shared/composables/useDebouncedValue'
import { getApiErrorMessage, getCollectionTotal } from '@/shared/utils/api'

const loading = ref(false)
const errorMessage = ref('')
const query = ref('')
const debouncedQuery = useDebouncedValue(query, 250)
const matches = ref<JobMatch[]>([])
const totalMatches = ref(0)
const detailsDialogVisible = ref(false)
const selectedMatch = ref<JobMatch | null>(null)

const filteredMatches = computed(() => {
  const search = debouncedQuery.value.trim().toLowerCase()

  return matches.value.filter((match) => {
    if (!search) {
      return true
    }

    return [
      match.job?.title || '',
      match.job?.company_name || '',
      match.candidate_profile?.full_name || '',
    ].some((value) => value.toLowerCase().includes(search))
  })
})

const metrics = computed(() => {
  if (!selectedMatch.value) {
    return []
  }

  return [
    { label: 'Skill Score', value: normalizeScore(selectedMatch.value.skill_score) },
    { label: 'Title Score', value: normalizeScore(selectedMatch.value.title_score) },
    { label: 'Seniority Score', value: normalizeScore(selectedMatch.value.seniority_score) },
    { label: 'Location Score', value: normalizeScore(selectedMatch.value.location_score) },
    { label: 'Backend Focus Score', value: normalizeScore(selectedMatch.value.backend_focus_score) },
    { label: 'Domain Score', value: normalizeScore(selectedMatch.value.domain_score) },
  ]
})

onMounted(async () => {
  await loadMatches()
})

async function loadMatches(): Promise<void> {
  loading.value = true
  errorMessage.value = ''

  try {
    const collection = await listMatches()
    matches.value = collection.items
    totalMatches.value = getCollectionTotal(collection)
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error, 'Failed to load matches.')
  } finally {
    loading.value = false
  }
}

function openDetails(match: JobMatch): void {
  selectedMatch.value = match
  detailsDialogVisible.value = true
}

function normalizeScore(value?: number | null): number {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    return 0
  }

  return value <= 1 ? Math.round(value * 100) : Math.round(value)
}

function formatDateTime(value?: string | null): string {
  if (!value) {
    return 'N/A'
  }

  return new Intl.DateTimeFormat('en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}
</script>
