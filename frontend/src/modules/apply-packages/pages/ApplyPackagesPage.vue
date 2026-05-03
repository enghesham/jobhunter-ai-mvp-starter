<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Apply"
      title="Apply Packages"
      description="Return to saved apply packages with tailored resumes, cover letters, application answers, and follow-up content."
    >
      <template #actions>
        <RouterLink to="/opportunities">
          <Button label="Find Opportunities" icon="pi pi-sparkles" severity="secondary" />
        </RouterLink>
      </template>
    </PageHeader>

    <div class="grid gap-4 md:grid-cols-4">
      <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Total packages</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ packages.length }}</p>
      </div>
      <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
        <p class="text-sm text-emerald-700">Ready</p>
        <p class="mt-2 text-3xl font-semibold text-emerald-950">{{ readyCount }}</p>
      </div>
      <div class="rounded-3xl border border-sky-200 bg-sky-50 p-5 shadow-sm">
        <p class="text-sm text-sky-700">Applications created</p>
        <p class="mt-2 text-3xl font-semibold text-sky-950">{{ createdApplicationCount }}</p>
      </div>
      <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
        <p class="text-sm text-amber-700">Fallback generated</p>
        <p class="mt-2 text-3xl font-semibold text-amber-950">{{ fallbackCount }}</p>
      </div>
    </div>

    <ErrorState v-if="errorMessage" title="Apply packages unavailable" :message="errorMessage">
      <template #actions>
        <Button label="Retry" icon="pi pi-refresh" @click="loadPackages" />
      </template>
    </ErrorState>

    <SkeletonTable v-if="loading" :columns="7" />

    <div v-else class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <IconField class="w-full xl:max-w-md">
          <InputIcon class="pi pi-search" />
          <InputText v-model.trim="query" fluid placeholder="Search job, company, profile, or path" />
        </IconField>

        <div class="flex flex-wrap gap-3">
          <Select
            v-model="statusFilter"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            class="w-full sm:w-56"
            placeholder="Filter by status"
          />
          <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
            Showing {{ filteredPackages.length }} of {{ packages.length }}
          </div>
        </div>
      </div>

      <EmptyState
        v-if="filteredPackages.length === 0"
        title="No apply packages yet"
        description="Evaluate a strong opportunity, then create an apply package. Saved packages will appear here even after closing the modal."
        icon="pi-file-edit"
      >
        <template #actions>
          <RouterLink to="/opportunities">
            <Button label="Go to Opportunities" icon="pi pi-sparkles" />
          </RouterLink>
        </template>
      </EmptyState>

      <DataTable
        v-else
        :value="filteredPackages"
        data-key="id"
        paginator
        :rows="10"
        class="mt-6"
        striped-rows
        responsive-layout="scroll"
      >
        <Column header="Job">
          <template #body="{ data }">
            <div class="max-w-md">
              <p class="font-semibold text-slate-900">{{ data.job?.title || `Job #${data.job_id}` }}</p>
              <p class="text-sm text-slate-500">{{ data.job?.company_name || 'Unknown company' }}</p>
              <div class="mt-2 flex flex-wrap gap-2">
                <Tag v-if="data.job_path?.name" :value="data.job_path.name" severity="info" />
                <Tag v-if="data.fallback_used" value="Fallback" severity="warn" />
              </div>
            </div>
          </template>
        </Column>

        <Column header="Profile">
          <template #body="{ data }">
            <div>
              <p class="font-medium text-slate-900">{{ data.career_profile?.full_name || `Profile #${data.career_profile_id}` }}</p>
              <p class="text-sm text-slate-500">{{ data.career_profile?.headline || 'Career profile' }}</p>
            </div>
          </template>
        </Column>

        <Column header="Status">
          <template #body="{ data }">
            <StatusTag :value="data.status" />
          </template>
        </Column>

        <Column header="Package Content">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-2">
              <Tag :value="data.cover_letter ? 'Cover letter' : 'No cover letter'" :severity="data.cover_letter ? 'success' : 'secondary'" />
              <Tag :value="`${normalizedAnswers(data.application_answers).length} answers`" severity="info" />
              <Tag :value="`${data.interview_questions?.length || 0} interview prep`" severity="warn" />
            </div>
          </template>
        </Column>

        <Column header="Created">
          <template #body="{ data }">
            {{ formatDateTime(data.created_at) }}
          </template>
        </Column>

        <Column header="Actions" :style="{ width: '20rem' }">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-2">
              <Button label="View" icon="pi pi-eye" size="small" text @click="openDetails(data)" />
              <Button
                v-if="data.resume?.id"
                label="PDF"
                icon="pi pi-download"
                size="small"
                text
                :loading="downloadingResumeId === data.resume.id"
                @click="downloadPackageResume(data)"
              />
              <Button
                v-if="!data.application_id"
                label="Create Application"
                icon="pi pi-send"
                size="small"
                severity="success"
                :loading="creatingApplicationPackageId === data.id"
                @click="createApplication(data)"
              />
              <RouterLink v-else to="/applications">
                <Button label="Open Application" icon="pi pi-external-link" size="small" severity="secondary" text />
              </RouterLink>
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Dialog v-model:visible="detailsVisible" modal header="Apply Package Details" :style="{ width: '64rem' }">
      <div v-if="selectedPackage" class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="text-sm font-medium uppercase tracking-[0.22em] text-emerald-700">Saved package</p>
            <h3 class="mt-2 text-2xl font-semibold text-slate-900">{{ selectedPackage.job?.title || `Job #${selectedPackage.job_id}` }}</h3>
            <p class="mt-1 text-sm text-slate-500">
              {{ selectedPackage.job?.company_name || 'Unknown company' }}
              <span v-if="selectedPackage.job_path?.name"> - {{ selectedPackage.job_path.name }}</span>
            </p>
          </div>

          <div class="flex flex-wrap gap-2">
            <Button
              v-if="selectedPackage.resume?.id"
              label="Download Resume PDF"
              icon="pi pi-download"
              severity="secondary"
              outlined
              :loading="downloadingResumeId === selectedPackage.resume.id"
              @click="downloadPackageResume(selectedPackage)"
            />
            <Button
              v-if="!selectedPackage.application_id"
              label="Create Application"
              icon="pi pi-send"
              severity="success"
              :loading="creatingApplicationPackageId === selectedPackage.id"
              @click="createApplication(selectedPackage)"
            />
            <RouterLink v-else to="/applications">
              <Button label="Open Application" icon="pi pi-external-link" severity="secondary" outlined />
            </RouterLink>
          </div>
        </div>

        <div class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Status</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedPackage.status }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Profile</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedPackage.career_profile?.full_name || `Profile #${selectedPackage.career_profile_id}` }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Provider</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedPackage.ai_provider || 'Deterministic fallback' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Created</p>
            <p class="mt-1 font-medium text-slate-900">{{ formatDateTime(selectedPackage.created_at) }}</p>
          </div>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h4 class="font-semibold text-slate-900">Cover Letter</h4>
            <Button label="Copy" icon="pi pi-copy" size="small" text @click="copyPackageText(selectedPackage.cover_letter)" />
          </div>
          <p class="whitespace-pre-line text-sm leading-6 text-slate-700">{{ selectedPackage.cover_letter || 'No cover letter generated.' }}</p>
        </section>

        <div class="grid gap-4 lg:grid-cols-3">
          <section class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="font-semibold text-slate-900">Why Interested</h4>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ selectedPackage.interest_answer || 'No answer generated.' }}</p>
          </section>
          <section class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="font-semibold text-slate-900">Salary Answer</h4>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ selectedPackage.salary_answer || 'No answer generated.' }}</p>
          </section>
          <section class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="font-semibold text-slate-900">Notice Period</h4>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ selectedPackage.notice_period_answer || 'No answer generated.' }}</p>
          </section>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <section class="rounded-3xl border border-emerald-200 bg-emerald-50 p-4">
            <h4 class="font-semibold text-slate-900">Strengths</h4>
            <div class="mt-3 flex flex-wrap gap-2">
              <Tag v-for="item in selectedPackage.strengths || []" :key="item" :value="item" severity="success" />
              <span v-if="!selectedPackage.strengths?.length" class="text-sm text-slate-500">No strengths listed.</span>
            </div>
          </section>
          <section class="rounded-3xl border border-amber-200 bg-amber-50 p-4">
            <h4 class="font-semibold text-slate-900">Gaps</h4>
            <div class="mt-3 flex flex-wrap gap-2">
              <Tag v-for="item in selectedPackage.gaps || []" :key="item" :value="item" severity="warn" />
              <span v-if="!selectedPackage.gaps?.length" class="text-sm text-slate-500">No major gaps listed.</span>
            </div>
          </section>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <section class="rounded-3xl border border-slate-200 bg-white p-4">
            <h4 class="font-semibold text-slate-900">Application Answers</h4>
            <div class="mt-3 space-y-3">
              <div v-for="answer in normalizedAnswers(selectedPackage.application_answers)" :key="answer.key || answer.title" class="rounded-2xl bg-slate-50 p-3">
                <div class="flex items-start justify-between gap-3">
                  <p class="font-medium text-slate-900">{{ answer.title || answer.question || answer.key || 'Application answer' }}</p>
                  <Button icon="pi pi-copy" size="small" text rounded @click="copyPackageText(answer.answer || answer.content)" />
                </div>
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ answer.answer || answer.content }}</p>
              </div>
              <p v-if="normalizedAnswers(selectedPackage.application_answers).length === 0" class="text-sm text-slate-500">No short answers generated.</p>
            </div>
          </section>

          <section class="rounded-3xl border border-slate-200 bg-white p-4">
            <h4 class="font-semibold text-slate-900">Interview Prep</h4>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
              <li v-for="question in selectedPackage.interview_questions || []" :key="question">{{ question }}</li>
              <li v-if="!selectedPackage.interview_questions?.length">No interview questions generated.</li>
            </ul>
          </section>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h4 class="font-semibold text-slate-900">Follow-up Email</h4>
            <Button label="Copy" icon="pi pi-copy" size="small" text @click="copyPackageText(selectedPackage.follow_up_email)" />
          </div>
          <p class="whitespace-pre-line text-sm leading-6 text-slate-700">{{ selectedPackage.follow_up_email || 'No follow-up email generated.' }}</p>
        </section>
      </div>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'

import {
  createApplicationFromApplyPackage,
  listApplyPackages,
} from '@/modules/apply-packages/services/applyPackagesApi'
import type { ApplyPackage, ApplyPackageAnswer, ApplyPackageStatus } from '@/modules/apply-packages/types'
import { downloadResumePdf } from '@/modules/resumes/services/resumesApi'
import EmptyState from '@/shared/components/EmptyState.vue'
import ErrorState from '@/shared/components/ErrorState.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
import SkeletonTable from '@/shared/components/SkeletonTable.vue'
import StatusTag from '@/shared/components/StatusTag.vue'
import { useDebouncedValue } from '@/shared/composables/useDebouncedValue'
import { getApiErrorMessage } from '@/shared/utils/api'
import { copyText } from '@/shared/utils/clipboard'

const toast = useToast()
const loading = ref(false)
const errorMessage = ref('')
const query = ref('')
const debouncedQuery = useDebouncedValue(query, 250)
const statusFilter = ref<ApplyPackageStatus | 'all'>('all')
const packages = ref<ApplyPackage[]>([])
const selectedPackage = ref<ApplyPackage | null>(null)
const detailsVisible = ref(false)
const downloadingResumeId = ref<number | null>(null)
const creatingApplicationPackageId = ref<number | null>(null)

const statusOptions = [
  { label: 'All statuses', value: 'all' },
  { label: 'Draft', value: 'draft' },
  { label: 'Ready', value: 'ready' },
  { label: 'Used', value: 'used' },
  { label: 'Archived', value: 'archived' },
]

const readyCount = computed(() => packages.value.filter((item) => item.status === 'ready').length)
const createdApplicationCount = computed(() => packages.value.filter((item) => item.application_id).length)
const fallbackCount = computed(() => packages.value.filter((item) => item.fallback_used).length)

const filteredPackages = computed(() => {
  const search = debouncedQuery.value.trim().toLowerCase()

  return packages.value.filter((item) => {
    if (statusFilter.value !== 'all' && item.status !== statusFilter.value) {
      return false
    }

    if (!search) {
      return true
    }

    return [
      item.job?.title || '',
      item.job?.company_name || '',
      item.career_profile?.full_name || '',
      item.career_profile?.headline || '',
      item.job_path?.name || '',
    ].some((value) => value.toLowerCase().includes(search))
  })
})

onMounted(async () => {
  await loadPackages()
})

async function loadPackages(): Promise<void> {
  loading.value = true
  errorMessage.value = ''

  try {
    const collection = await listApplyPackages()
    packages.value = collection.items
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error, 'Failed to load apply packages.')
  } finally {
    loading.value = false
  }
}

function openDetails(applyPackage: ApplyPackage): void {
  selectedPackage.value = applyPackage
  detailsVisible.value = true
}

async function downloadPackageResume(applyPackage: ApplyPackage): Promise<void> {
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

async function createApplication(applyPackage: ApplyPackage): Promise<void> {
  creatingApplicationPackageId.value = applyPackage.id

  try {
    const application = await createApplicationFromApplyPackage(applyPackage.id)
    const updatedPackage: ApplyPackage = {
      ...applyPackage,
      application_id: application.id,
      application,
      status: 'used',
    }

    packages.value = packages.value.map((item) => item.id === applyPackage.id ? updatedPackage : item)

    if (selectedPackage.value?.id === applyPackage.id) {
      selectedPackage.value = updatedPackage
    }

    toast.add({
      severity: 'success',
      summary: 'Application created',
      detail: 'The package is now linked to your application tracker.',
      life: 4000,
    })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Application failed', detail: getApiErrorMessage(error), life: 5000 })
  } finally {
    creatingApplicationPackageId.value = null
  }
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
