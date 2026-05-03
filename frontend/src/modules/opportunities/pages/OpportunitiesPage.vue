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

    <div class="grid gap-4 md:grid-cols-4">
      <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Visible opportunities</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ opportunities.length }}</p>
      </div>
      <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Already evaluated</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ evaluatedCount }}</p>
      </div>
      <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
        <p class="text-sm text-emerald-700">Best matches</p>
        <p class="mt-2 text-3xl font-semibold text-emerald-950">{{ bestMatchCount }}</p>
        <p class="mt-1 text-xs text-emerald-700">Evaluated jobs that passed the apply threshold.</p>
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
          <label class="flex items-center gap-2 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <Checkbox v-model="bestMatchesOnly" binary />
            Best matches only
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
                v-if="isFitOpportunity(data)"
                label="Create Apply Package"
                icon="pi pi-file-edit"
                size="small"
                severity="success"
                :loading="generatingPackageOpportunityId === data.id"
                @click="createPackage(data)"
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
              {{ nextActionText(selectedOpportunity) }}
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
              <Button
                v-if="!selectedOpportunity.match_id"
                label="Evaluate Fit"
                icon="pi pi-sparkles"
                size="small"
                :loading="evaluatingId === selectedOpportunity.id"
                @click="evaluate(selectedOpportunity)"
              />
              <Button
                v-if="isFitOpportunity(selectedOpportunity)"
                label="Create Apply Package"
                icon="pi pi-file-edit"
                size="small"
                severity="success"
                :loading="generatingPackageOpportunityId === selectedOpportunity.id"
                @click="createPackage(selectedOpportunity)"
              />
              <RouterLink v-if="selectedOpportunity.match_id" to="/matches">
                <Button label="Open Best Matches" icon="pi pi-star" size="small" text />
              </RouterLink>
            </div>
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

    <Dialog v-model:visible="applyPackageVisible" modal header="Apply Package" :style="{ width: '64rem' }">
      <div v-if="selectedApplyPackage" class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="text-sm font-medium uppercase tracking-[0.22em] text-emerald-700">Ready to apply</p>
            <h3 class="mt-2 text-2xl font-semibold text-slate-900">
              {{ selectedApplyPackage.job?.title || selectedOpportunity?.job?.title || `Job #${selectedApplyPackage.job_id}` }}
            </h3>
            <p class="mt-1 text-sm text-slate-500">
              {{ selectedApplyPackage.job?.company_name || selectedOpportunity?.job?.company_name || 'Unknown company' }}
              <span v-if="selectedApplyPackage.job_path?.name">آ· {{ selectedApplyPackage.job_path.name }}</span>
            </p>
          </div>

          <div class="flex flex-wrap gap-2">
            <Button
              v-if="selectedApplyPackage.resume?.download_pdf_url || selectedApplyPackage.resume?.pdf_url"
              label="Download Resume PDF"
              icon="pi pi-download"
              severity="secondary"
              outlined
              :loading="downloadingResumeId === selectedApplyPackage.resume?.id"
              @click="downloadPackageResumePdf(selectedApplyPackage)"
            />
            <Button
              label="Create Application"
              icon="pi pi-send"
              severity="success"
              :loading="creatingApplication"
              :disabled="Boolean(selectedApplyPackage.application_id)"
              @click="createApplicationFromPackage"
            />
          </div>
        </div>

        <div class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Status</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedApplyPackage.status }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Profile</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedApplyPackage.career_profile?.full_name || `Profile #${selectedApplyPackage.career_profile_id}` }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Provider</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedApplyPackage.ai_provider || 'Deterministic fallback' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Application</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedApplyPackage.application_id ? `#${selectedApplyPackage.application_id}` : 'Not created yet' }}</p>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h4 class="font-semibold text-slate-900">Cover Letter</h4>
            <Button label="Copy" icon="pi pi-copy" size="small" text @click="copyPackageText(selectedApplyPackage.cover_letter)" />
          </div>
          <p class="whitespace-pre-line text-sm leading-6 text-slate-700">{{ selectedApplyPackage.cover_letter || 'No cover letter generated.' }}</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="font-semibold text-slate-900">Why Interested</h4>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ selectedApplyPackage.interest_answer || 'No answer generated.' }}</p>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="font-semibold text-slate-900">Salary Answer</h4>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ selectedApplyPackage.salary_answer || 'No answer generated.' }}</p>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="font-semibold text-slate-900">Notice Period</h4>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ selectedApplyPackage.notice_period_answer || 'No answer generated.' }}</p>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-4">
            <h4 class="font-semibold text-slate-900">Strengths for this role</h4>
            <div class="mt-3 flex flex-wrap gap-2">
              <Tag v-for="item in selectedApplyPackage.strengths || []" :key="item" :value="item" severity="success" />
              <span v-if="!selectedApplyPackage.strengths?.length" class="text-sm text-slate-500">No strengths listed.</span>
            </div>
          </div>
          <div class="rounded-3xl border border-amber-200 bg-amber-50 p-4">
            <h4 class="font-semibold text-slate-900">Missing skills warning</h4>
            <div class="mt-3 flex flex-wrap gap-2">
              <Tag v-for="item in selectedApplyPackage.gaps || []" :key="item" :value="item" severity="warn" />
              <span v-if="!selectedApplyPackage.gaps?.length" class="text-sm text-slate-500">No major gaps listed.</span>
            </div>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-3xl border border-slate-200 bg-white p-4">
            <h4 class="font-semibold text-slate-900">Short application answers</h4>
            <div class="mt-3 space-y-3">
              <div v-for="answer in normalizedAnswers(selectedApplyPackage.application_answers)" :key="answer.key || answer.title" class="rounded-2xl bg-slate-50 p-3">
                <p class="font-medium text-slate-900">{{ answer.title || answer.question || answer.key || 'Application answer' }}</p>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ answer.answer || answer.content }}</p>
              </div>
              <p v-if="normalizedAnswers(selectedApplyPackage.application_answers).length === 0" class="text-sm text-slate-500">No short answers generated.</p>
            </div>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-white p-4">
            <h4 class="font-semibold text-slate-900">Interview prep questions</h4>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
              <li v-for="question in selectedApplyPackage.interview_questions || []" :key="question">{{ question }}</li>
              <li v-if="!selectedApplyPackage.interview_questions?.length">No interview questions generated.</li>
            </ul>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h4 class="font-semibold text-slate-900">Follow-up Email</h4>
            <Button label="Copy" icon="pi pi-copy" size="small" text @click="copyPackageText(selectedApplyPackage.follow_up_email)" />
          </div>
          <p class="whitespace-pre-line text-sm leading-6 text-slate-700">{{ selectedApplyPackage.follow_up_email || 'No follow-up email generated.' }}</p>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
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
import {
  createApplicationFromApplyPackage,
  generateApplyPackage,
} from '@/modules/apply-packages/services/applyPackagesApi'
import type { ApplyPackage, ApplyPackageAnswer } from '@/modules/apply-packages/types'
import type { JobOpportunity } from '@/modules/opportunities/types'
import { downloadResumePdf } from '@/modules/resumes/services/resumesApi'
import EmptyState from '@/shared/components/EmptyState.vue'
import ErrorState from '@/shared/components/ErrorState.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
import ScoreBadge from '@/shared/components/ScoreBadge.vue'
import SkeletonTable from '@/shared/components/SkeletonTable.vue'
import { useDebouncedValue } from '@/shared/composables/useDebouncedValue'
import { getApiErrorMessage } from '@/shared/utils/api'
import { copyText } from '@/shared/utils/clipboard'

const toast = useToast()
const router = useRouter()
const loading = ref(false)
const refreshing = ref(false)
const evaluatingId = ref<number | null>(null)
const generatingPackageOpportunityId = ref<number | null>(null)
const creatingApplication = ref(false)
const downloadingResumeId = ref<number | null>(null)
const errorMessage = ref('')
const query = ref('')
const debouncedQuery = useDebouncedValue(query, 250)
const includeHidden = ref(false)
const showDuplicates = ref(false)
const bestMatchesOnly = ref(false)
const opportunities = ref<JobOpportunity[]>([])
const selectedOpportunity = ref<JobOpportunity | null>(null)
const selectedApplyPackage = ref<ApplyPackage | null>(null)
const detailsVisible = ref(false)
const applyPackageVisible = ref(false)

const evaluatedCount = computed(() => opportunities.value.filter((opportunity) => opportunity.match_id).length)
const pendingCount = computed(() => opportunities.value.filter((opportunity) => !opportunity.match_id).length)
const bestMatchCount = computed(() => opportunities.value.filter((opportunity) => isFitOpportunity(opportunity)).length)

const filteredOpportunities = computed(() => {
  const search = debouncedQuery.value.trim().toLowerCase()
  const pool = bestMatchesOnly.value
    ? opportunities.value.filter((opportunity) => isFitOpportunity(opportunity))
    : opportunities.value

  if (!search) {
    return pool
  }

  return pool.filter((opportunity) => [
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

async function createPackage(opportunity: JobOpportunity): Promise<void> {
  if (!isFitOpportunity(opportunity)) {
    toast.add({
      severity: 'warn',
      summary: 'Evaluate fit first',
      detail: 'Create an apply package only after the job passes your match threshold.',
      life: 4000,
    })
    return
  }

  generatingPackageOpportunityId.value = opportunity.id

  try {
    const applyPackage = await generateApplyPackage(opportunity.job_id, {
      career_profile_id: opportunity.career_profile_id ?? opportunity.match?.candidate_profile_id ?? opportunity.match?.profile_id ?? null,
      job_path_id: opportunity.job_path_id ?? opportunity.match?.job_path_id ?? null,
    })

    selectedOpportunity.value = opportunity
    selectedApplyPackage.value = applyPackage
    applyPackageVisible.value = true

    toast.add({
      severity: 'success',
      summary: 'Apply package ready',
      detail: 'Resume, cover letter, answers, and follow-up content were prepared.',
      life: 4000,
    })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Apply package failed', detail: getApiErrorMessage(error), life: 5000 })
  } finally {
    generatingPackageOpportunityId.value = null
  }
}

async function createApplicationFromPackage(): Promise<void> {
  if (!selectedApplyPackage.value) {
    return
  }

  creatingApplication.value = true

  try {
    const application = await createApplicationFromApplyPackage(selectedApplyPackage.value.id)
    selectedApplyPackage.value = {
      ...selectedApplyPackage.value,
      application_id: application.id,
      application,
      status: 'used',
    }

    toast.add({
      severity: 'success',
      summary: 'Application created',
      detail: 'The apply package was saved into your application tracker.',
      life: 4000,
    })

    await router.push('/applications')
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Application failed', detail: getApiErrorMessage(error), life: 5000 })
  } finally {
    creatingApplication.value = false
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

function isFitOpportunity(opportunity: JobOpportunity): boolean {
  if (!opportunity.match_id) {
    return false
  }

  const action = opportunity.match?.recommendation_action ?? opportunity.recommendation

  if (action === 'skip') {
    return false
  }

  if (opportunity.status === 'recommended' || action === 'apply') {
    return true
  }

  return normalizeScore(opportunity.match_score ?? opportunity.match?.overall_score) >= matchThreshold(opportunity)
}

function matchThreshold(opportunity: JobOpportunity): number {
  return opportunity.job_path?.min_match_score ?? 75
}

function normalizeScore(value?: number | null): number {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    return 0
  }

  return value <= 1 ? Math.round(value * 100) : Math.round(value)
}

function nextActionText(opportunity: JobOpportunity): string {
  if (!opportunity.match_id) {
    return 'Run Evaluate Fit only if this job looks worth deeper AI analysis.'
  }

  if (isFitOpportunity(opportunity)) {
    return 'This job passed your threshold. Create an apply package with a tailored CV, cover letter, answers, and follow-up email.'
  }

  return 'This job was evaluated but did not pass your apply threshold. Keep it in history or hide it from your main list.'
}

function normalizedAnswers(value: ApplyPackage['application_answers']): ApplyPackageAnswer[] {
  if (Array.isArray(value)) {
    return value
  }

  if (!value || typeof value !== 'object') {
    return []
  }

  return Object.entries(value).map(([key, content]) => ({
    key,
    title: key.replaceAll('_', ' '),
    content: String(content ?? ''),
  }))
}

async function copyPackageText(value?: string | null): Promise<void> {
  if (!value) {
    toast.add({ severity: 'warn', summary: 'Nothing to copy', life: 2500 })
    return
  }

  await copyText(value)
  toast.add({ severity: 'success', summary: 'Copied', life: 2500 })
}

async function downloadPackageResumePdf(applyPackage: ApplyPackage): Promise<void> {
  if (!applyPackage.resume?.id) {
    return
  }

  downloadingResumeId.value = applyPackage.resume.id

  try {
    await downloadResumePdf(applyPackage.resume.id)
  } catch (error) {
    toast.add({ severity: 'error', summary: 'PDF download failed', detail: getApiErrorMessage(error), life: 5000 })
  } finally {
    downloadingResumeId.value = null
  }
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
