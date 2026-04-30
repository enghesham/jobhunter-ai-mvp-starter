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

    <SkeletonTable v-if="loading" :columns="7" />

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
            <div class="space-y-1">
              <StatusTag :value="data.recommendation" />
              <StatusTag v-if="data.recommendation_action" :value="data.recommendation_action" :label="decisionLabel(data.recommendation_action)" />
            </div>
          </template>
        </Column>

        <Column header="Fit Signals">
          <template #body="{ data }">
            <div class="space-y-2">
              <div class="flex flex-wrap gap-2">
                <Tag
                  v-for="item in previewItems(data.strength_areas, 2)"
                  :key="`${data.id}-strength-${item}`"
                  severity="success"
                  :value="item"
                />
              </div>
              <div class="flex flex-wrap gap-2">
                <Tag
                  v-for="item in previewItems(data.missing_required_skills || data.missing_skills, 2)"
                  :key="`${data.id}-missing-${item}`"
                  severity="danger"
                  :value="item"
                />
                <span
                  v-if="!(data.strength_areas?.length || data.missing_required_skills?.length || data.missing_skills?.length)"
                  class="text-xs text-slate-400"
                >
                  Detailed explanation available in preview
                </span>
              </div>
            </div>
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

        <div class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Candidate</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedMatch.candidate_profile?.full_name || `Profile #${selectedMatch.profile_id}` }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Matched At</p>
            <p class="mt-1 font-medium text-slate-900">{{ formatDateTime(selectedMatch.matched_at || selectedMatch.created_at) }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Provider</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedMatch.ai_provider || 'Deterministic fallback' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Confidence</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedMatch.ai_confidence_score ?? 0 }}%</p>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
          <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-4">
            <h4 class="mb-2 text-lg font-semibold text-slate-900">Why This Fits</h4>
            <p class="text-sm leading-6 text-slate-700">{{ selectedMatch.why_matched || selectedMatch.notes || 'No fit explanation available.' }}</p>
          </div>
          <div class="rounded-3xl border border-amber-200 bg-amber-50 p-4">
            <h4 class="mb-2 text-lg font-semibold text-slate-900">What Is Missing</h4>
            <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
              <li v-for="item in selectedMatch.missing_required_skills || selectedMatch.missing_skills || []" :key="item">{{ item }}</li>
              <li v-if="!(selectedMatch.missing_required_skills?.length || selectedMatch.missing_skills?.length)">No required skill gaps were flagged.</li>
            </ul>
          </div>
          <div class="rounded-3xl border border-sky-200 bg-sky-50 p-4">
            <h4 class="mb-2 text-lg font-semibold text-slate-900">Should I Apply?</h4>
            <div class="flex items-center gap-3">
              <StatusTag
                :value="selectedMatch.recommendation_action"
                :label="decisionLabel(selectedMatch.recommendation_action)"
              />
              <p class="text-sm leading-6 text-slate-700">{{ applySummary(selectedMatch) }}</p>
            </div>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Why Matched</h4>
            <p class="text-sm leading-6 text-slate-700">{{ selectedMatch.why_matched || 'No detailed explanation available.' }}</p>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Recommendation Summary</h4>
            <p class="text-sm leading-6 text-slate-700">{{ selectedMatch.ai_recommendation_summary || selectedMatch.notes || 'No recommendation summary available.' }}</p>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Strength Areas</h4>
            <div class="flex flex-wrap gap-2">
              <StatusTag v-for="item in selectedMatch.strength_areas || []" :key="item" :label="item" :value="'matched'" />
            </div>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Missing Skills</h4>
            <div class="flex flex-wrap gap-2">
              <StatusTag v-for="item in selectedMatch.missing_required_skills || selectedMatch.missing_skills || []" :key="item" :label="item" :value="'rejected'" />
            </div>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Nice-To-Have Gaps</h4>
            <div class="flex flex-wrap gap-2">
              <StatusTag v-for="item in selectedMatch.nice_to_have_gaps || []" :key="item" :label="item" :value="'custom'" />
            </div>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Risk Flags</h4>
            <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
              <li v-for="item in selectedMatch.risk_flags || []" :key="item">{{ item }}</li>
            </ul>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 lg:col-span-1">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Resume Focus Points</h4>
            <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
              <li v-for="item in selectedMatch.resume_focus_points || []" :key="item">{{ item }}</li>
            </ul>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">AI Metadata</h4>
          <div class="grid gap-3 text-sm text-slate-700 md:grid-cols-2 xl:grid-cols-3">
            <p><span class="font-medium text-slate-900">Provider:</span> {{ selectedMatch.ai_provider || 'Deterministic fallback' }}</p>
            <p><span class="font-medium text-slate-900">Model:</span> {{ selectedMatch.ai_model || 'N/A' }}</p>
            <p><span class="font-medium text-slate-900">Prompt:</span> {{ selectedMatch.prompt_version || 'N/A' }}</p>
            <p><span class="font-medium text-slate-900">Duration:</span> {{ formatDuration(selectedMatch.ai_duration_ms) }}</p>
            <p><span class="font-medium text-slate-900">Fallback:</span> {{ yesNo(selectedMatch.fallback_used) }}</p>
            <p><span class="font-medium text-slate-900">Generated At:</span> {{ formatDateTime(selectedMatch.ai_generated_at) }}</p>
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
import Tag from 'primevue/tag'
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
    { label: 'Experience Score', value: normalizeScore(selectedMatch.value.experience_score) },
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

function formatDuration(value?: number | null): string {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    return 'N/A'
  }

  return `${value} ms`
}

function yesNo(value?: boolean | null): string {
  return value ? 'Yes' : 'No'
}

function previewItems(items?: string[] | null, limit = 2): string[] {
  if (!Array.isArray(items)) {
    return []
  }

  return items.slice(0, limit)
}

function decisionLabel(action?: string | null): string {
  switch (action) {
    case 'apply':
      return 'Apply'
    case 'consider':
      return 'Consider'
    case 'skip':
      return 'Skip'
    default:
      return 'Undecided'
  }
}

function applySummary(match: JobMatch): string {
  switch (match.recommendation_action) {
    case 'apply':
      return 'The fit is strong enough to proceed with a real application.'
    case 'consider':
      return 'There is meaningful alignment, but review the gaps before applying.'
    case 'skip':
      return 'The current gap profile is large enough that this is not a priority application.'
    default:
      return 'No clear application recommendation was generated.'
  }
}
</script>
