<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Copilot"
      title="Opportunities"
      description="Review jobs pre-screened by your Job Paths. Run AI evaluation only on the opportunities you care about."
    >
      <template #actions>
        <Button label="Refresh Opportunities" icon="pi pi-refresh" :loading="refreshing" @click="refreshList" />
      </template>
    </PageHeader>

    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Visible opportunities</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ opportunities.length }}</p>
      </div>
      <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Already evaluated</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ evaluatedCount }}</p>
      </div>
      <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">AI calls saved</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ pendingCount }}</p>
        <p class="mt-1 text-xs text-slate-500">Pending opportunities use cheap ranking until you evaluate them.</p>
      </div>
    </div>

    <ErrorState v-if="errorMessage" title="Opportunities unavailable" :message="errorMessage">
      <template #actions>
        <Button label="Retry" icon="pi pi-refresh" @click="loadOpportunities" />
      </template>
    </ErrorState>

    <SkeletonTable v-if="loading" :columns="7" />

    <div v-else class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <IconField class="w-full lg:max-w-sm">
          <InputIcon class="pi pi-search" />
          <InputText v-model.trim="query" fluid placeholder="Search title, company, path, or keyword" />
        </IconField>

        <div class="flex flex-wrap items-center gap-3">
          <label class="flex items-center gap-2 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
            <Checkbox v-model="includeHidden" binary @change="loadOpportunities" />
            Show hidden / not relevant
          </label>
          <label class="flex items-center gap-2 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
            <Checkbox v-model="showDuplicates" binary @change="loadOpportunities" />
            Show duplicates by path
          </label>
        </div>
      </div>

      <EmptyState
        v-if="filteredOpportunities.length === 0"
        title="No opportunities yet"
        description="Refresh opportunities after you have job paths and collected jobs. The system will pre-screen jobs without spending AI calls."
        icon="pi-compass"
      >
        <template #actions>
          <div class="flex flex-wrap justify-center gap-3">
            <Button label="Refresh Opportunities" icon="pi pi-refresh" :loading="refreshing" @click="refreshList" />
            <RouterLink to="/job-sources">
              <Button label="Collect Jobs" icon="pi pi-database" severity="secondary" />
            </RouterLink>
          </div>
        </template>
      </EmptyState>

      <DataTable
        v-else
        :value="filteredOpportunities"
        data-key="id"
        paginator
        :rows="10"
        class="mt-6"
        striped-rows
        responsive-layout="scroll"
      >
        <Column header="Opportunity">
          <template #body="{ data }">
            <div class="max-w-xl">
              <p class="font-semibold text-slate-900">{{ data.job?.title || `Job #${data.job_id}` }}</p>
              <p class="text-sm text-slate-500">{{ data.job?.company_name || 'Unknown company' }} · {{ data.job?.location || 'Location not listed' }}</p>
              <div class="mt-2 flex flex-wrap gap-2">
                <Tag v-if="data.job_path" :value="data.job_path.name" severity="info" />
                <Tag v-if="data.job?.remote_type || data.job?.is_remote" :value="data.job?.remote_type || 'remote'" severity="success" />
                <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
              </div>
            </div>
          </template>
        </Column>

        <Column header="Score">
          <template #body="{ data }">
            <ScoreBadge :score="data.display_score" :label="data.match_score ? 'Match' : 'Quick fit'" />
          </template>
        </Column>

        <Column header="Why shown">
          <template #body="{ data }">
            <ul class="max-w-md space-y-1 text-sm text-slate-600">
              <li v-for="reason in preview(data.reasons, 3)" :key="`${data.id}-${reason}`">{{ reason }}</li>
              <li v-if="!data.reasons?.length" class="text-slate-400">No quick reasons recorded.</li>
            </ul>
          </template>
        </Column>

        <Column header="Signals">
          <template #body="{ data }">
            <div class="flex max-w-sm flex-wrap gap-2">
              <Tag v-for="keyword in preview(data.matched_keywords, 4)" :key="`${data.id}-match-${keyword}`" :value="keyword" severity="success" />
              <Tag v-for="keyword in preview(data.missing_keywords, 2)" :key="`${data.id}-missing-${keyword}`" :value="keyword" severity="warn" />
            </div>
          </template>
        </Column>

        <Column header="Posted">
          <template #body="{ data }">
            {{ formatDate(data.job?.posted_at || data.job?.created_at) }}
          </template>
        </Column>

        <Column header="Actions" :style="{ width: '18rem' }">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-2">
              <Button label="Details" icon="pi pi-eye" size="small" text @click="openDetails(data)" />
              <Button
                label="Evaluate Fit"
                icon="pi pi-sparkles"
                size="small"
                :loading="evaluatingId === data.id"
                :disabled="data.status === 'hidden'"
                @click="evaluate(data)"
              />
              <Button
                v-if="data.status !== 'hidden'"
                icon="pi pi-eye-slash"
                size="small"
                severity="secondary"
                text
                rounded
                @click="hide(data)"
              />
              <Button
                v-else
                icon="pi pi-undo"
                size="small"
                severity="secondary"
                text
                rounded
                @click="restore(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Dialog v-model:visible="detailsVisible" modal header="Opportunity Details" :style="{ width: '52rem' }">
      <div v-if="selectedOpportunity" class="space-y-5">
        <div>
          <h3 class="text-2xl font-semibold text-slate-900">{{ selectedOpportunity.job?.title }}</h3>
          <p class="mt-1 text-sm text-slate-500">{{ selectedOpportunity.job?.company_name }} · {{ selectedOpportunity.job?.location || 'Location not listed' }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
          <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Job Path</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedOpportunity.job_path?.name || 'Primary profile' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Quick Score</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedOpportunity.quick_relevance_score }}%</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Match Score</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedOpportunity.match_score ?? 'Not evaluated' }}</p>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-4">
            <h4 class="font-semibold text-slate-900">Why this appeared</h4>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-700">
              <li v-for="reason in selectedOpportunity.reasons" :key="reason">{{ reason }}</li>
            </ul>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="font-semibold text-slate-900">Next best action</h4>
            <p class="mt-3 text-sm leading-6 text-slate-700">
              {{ selectedOpportunity.match_id ? 'This opportunity was evaluated. Review the match explanation or create an apply package.' : 'Run Evaluate Fit only if this job looks worth deeper AI analysis.' }}
            </p>
          </div>
        </div>

        <div v-if="selectedOpportunity.match" class="rounded-3xl border border-sky-200 bg-sky-50 p-4">
          <h4 class="font-semibold text-slate-900">Evaluation result</h4>
          <p class="mt-2 text-sm leading-6 text-slate-700">{{ selectedOpportunity.match.ai_recommendation_summary || selectedOpportunity.match.notes }}</p>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-4">
          <h4 class="font-semibold text-slate-900">Description</h4>
          <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ selectedOpportunity.job?.description_clean || selectedOpportunity.job?.description_raw || 'No description available.' }}</p>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'

import {
  evaluateOpportunity,
  hideOpportunity,
  listOpportunities,
  refreshOpportunities,
  restoreOpportunity,
} from '@/modules/opportunities/services/opportunitiesApi'
import type { JobOpportunity } from '@/modules/opportunities/types'
import EmptyState from '@/shared/components/EmptyState.vue'
import ErrorState from '@/shared/components/ErrorState.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
import ScoreBadge from '@/shared/components/ScoreBadge.vue'
import SkeletonTable from '@/shared/components/SkeletonTable.vue'
import { useDebouncedValue } from '@/shared/composables/useDebouncedValue'
import { getApiErrorMessage } from '@/shared/utils/api'

const toast = useToast()
const loading = ref(false)
const refreshing = ref(false)
const evaluatingId = ref<number | null>(null)
const errorMessage = ref('')
const query = ref('')
const debouncedQuery = useDebouncedValue(query, 250)
const includeHidden = ref(false)
const showDuplicates = ref(false)
const opportunities = ref<JobOpportunity[]>([])
const selectedOpportunity = ref<JobOpportunity | null>(null)
const detailsVisible = ref(false)

const evaluatedCount = computed(() => opportunities.value.filter((opportunity) => opportunity.match_id).length)
const pendingCount = computed(() => opportunities.value.filter((opportunity) => !opportunity.match_id).length)

const filteredOpportunities = computed(() => {
  const search = debouncedQuery.value.trim().toLowerCase()

  if (!search) {
    return opportunities.value
  }

  return opportunities.value.filter((opportunity) => [
    opportunity.job?.title || '',
    opportunity.job?.company_name || '',
    opportunity.job_path?.name || '',
    ...(opportunity.matched_keywords || []),
  ].some((value) => value.toLowerCase().includes(search)))
})

onMounted(async () => {
  await loadOpportunities()
})

async function loadOpportunities(): Promise<void> {
  loading.value = true
  errorMessage.value = ''

  try {
    const collection = await listOpportunities({
      includeHidden: includeHidden.value,
      showDuplicates: showDuplicates.value,
    })
    opportunities.value = collection.items
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error, 'Failed to load opportunities.')
  } finally {
    loading.value = false
  }
}

async function refreshList(): Promise<void> {
  refreshing.value = true

  try {
    const response = await refreshOpportunities()
    opportunities.value = response.opportunities
    toast.add({
      severity: 'success',
      summary: 'Opportunities refreshed',
      detail: `${response.stats.created} created, ${response.stats.updated} updated, ${response.stats.skipped} skipped, ${response.stats.evaluated} auto-evaluated.`,
      life: 4000,
    })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Refresh failed', detail: getApiErrorMessage(error), life: 5000 })
  } finally {
    refreshing.value = false
  }
}

async function evaluate(opportunity: JobOpportunity): Promise<void> {
  evaluatingId.value = opportunity.id

  try {
    const updated = await evaluateOpportunity(opportunity.id)
    replaceOpportunity(updated)
    selectedOpportunity.value = updated
    toast.add({ severity: 'success', summary: 'Fit evaluated', detail: 'Analysis and match were saved for this opportunity.', life: 4000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Evaluation failed', detail: getApiErrorMessage(error), life: 5000 })
  } finally {
    evaluatingId.value = null
  }
}

async function hide(opportunity: JobOpportunity): Promise<void> {
  try {
    const updated = await hideOpportunity(opportunity.id, 'Hidden by user')
    if (includeHidden.value) {
      replaceOpportunity(updated)
    } else {
      opportunities.value = opportunities.value.filter((item) => item.id !== opportunity.id)
    }
    toast.add({ severity: 'success', summary: 'Opportunity hidden', detail: 'It will stay out of your default list.', life: 3000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Could not hide', detail: getApiErrorMessage(error), life: 5000 })
  }
}

async function restore(opportunity: JobOpportunity): Promise<void> {
  try {
    replaceOpportunity(await restoreOpportunity(opportunity.id))
    toast.add({ severity: 'success', summary: 'Opportunity restored', life: 3000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Could not restore', detail: getApiErrorMessage(error), life: 5000 })
  }
}

function openDetails(opportunity: JobOpportunity): void {
  selectedOpportunity.value = opportunity
  detailsVisible.value = true
}

function replaceOpportunity(updated: JobOpportunity): void {
  opportunities.value = opportunities.value.map((item) => item.id === updated.id ? updated : item)
}

function preview(items?: string[] | null, limit = 3): string[] {
  return Array.isArray(items) ? items.slice(0, limit) : []
}

function formatDate(value?: string | null): string {
  if (!value) {
    return 'N/A'
  }

  return new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' }).format(new Date(value))
}

function statusLabel(status: string): string {
  return status.replaceAll('_', ' ')
}

function statusSeverity(status: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' {
  switch (status) {
    case 'recommended':
      return 'success'
    case 'evaluated':
      return 'info'
    case 'not_relevant':
      return 'danger'
    case 'hidden':
      return 'secondary'
    default:
      return 'warn'
  }
}
</script>
