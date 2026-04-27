<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Documents"
      title="Resumes"
      description="Review persisted tailored resumes created from the Jobs page, including preview links and structured sections."
    />

    <ErrorState v-if="errorMessage" title="Resumes unavailable" :message="errorMessage">
      <template #actions>
        <Button label="Retry" icon="pi pi-refresh" @click="loadResumes" />
      </template>
    </ErrorState>

    <SkeletonTable v-if="loading" :columns="5" />

    <div v-else class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <IconField class="w-full lg:max-w-sm">
          <InputIcon class="pi pi-search" />
          <InputText v-model.trim="query" fluid placeholder="Search by job title, candidate, or headline" />
        </IconField>

        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
          Showing {{ filteredResumes.length }} of {{ totalResumes }} resumes
        </div>
      </div>

      <EmptyState
        v-if="filteredResumes.length === 0"
        title="No generated resumes yet"
        description="Generate a tailored resume from the Jobs page and it will appear here automatically."
        icon="pi-file-edit"
      >
        <template #actions>
          <RouterLink to="/jobs">
            <Button label="Go to Jobs" icon="pi pi-briefcase" />
          </RouterLink>
        </template>
      </EmptyState>

      <DataTable
        v-else
        :value="filteredResumes"
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

        <Column header="Version">
          <template #body="{ data }">
            {{ data.version_name || 'v1' }}
          </template>
        </Column>

        <Column header="Created At">
          <template #body="{ data }">
            {{ formatDateTime(data.created_at) }}
          </template>
        </Column>

        <Column header="Resume URL">
          <template #body="{ data }">
            <Button
              v-if="previewUrl(data)"
              label="View"
              icon="pi pi-external-link"
              size="small"
              text
              @click="openUrl(previewUrl(data)!)"
            />
            <span v-else class="text-sm text-slate-500">No preview URL</span>
          </template>
        </Column>

        <Column header="Actions" :style="{ width: '10rem' }">
          <template #body="{ data }">
            <Button label="View Preview" icon="pi pi-eye" size="small" text @click="openDetails(data)" />
          </template>
        </Column>
      </DataTable>
    </div>

    <Dialog v-model:visible="detailsDialogVisible" modal header="Resume Preview" :style="{ width: '60rem' }">
      <div v-if="selectedResume" class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <h3 class="text-2xl font-semibold text-slate-900">{{ selectedResume.headline || 'Generated Resume' }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ selectedResume.job?.title || `Job #${selectedResume.job_id}` }} | {{ selectedResume.version_name || 'v1' }}</p>
          </div>

          <div class="flex flex-wrap gap-2">
            <Button
              v-if="previewUrl(selectedResume)"
              label="Open Preview"
              icon="pi pi-external-link"
              severity="secondary"
              outlined
              @click="openUrl(previewUrl(selectedResume)!)"
            />
          </div>
        </div>

        <div v-if="selectedResume.professional_summary" class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-2 text-lg font-semibold text-slate-900">Professional Summary</h4>
          <p class="text-sm leading-6 text-slate-700">{{ selectedResume.professional_summary }}</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Selected Skills</h4>
            <div class="flex flex-wrap gap-2">
              <Tag v-for="skill in selectedResume.selected_skills || []" :key="skill" :value="skill" severity="success" />
            </div>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">ATS Keywords</h4>
            <div class="flex flex-wrap gap-2">
              <Tag v-for="keyword in selectedResume.ats_keywords || []" :key="keyword" :value="keyword" severity="warn" />
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">Experience Bullets</h4>
          <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
            <li v-for="bullet in selectedResume.selected_experience_bullets || []" :key="bullet">{{ bullet }}</li>
          </ul>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">Projects</h4>
          <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
            <li v-for="project in selectedResume.selected_projects || []" :key="project">{{ project }}</li>
          </ul>
        </div>

        <div v-if="(selectedResume.warnings_or_gaps || []).length > 0" class="rounded-3xl border border-amber-200 bg-amber-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">Warnings / Gaps</h4>
          <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
            <li v-for="warning in selectedResume.warnings_or_gaps || []" :key="warning">{{ warning }}</li>
          </ul>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">AI Metadata</h4>
          <div class="grid gap-3 md:grid-cols-3 text-sm text-slate-700">
            <p><span class="font-medium text-slate-900">Provider:</span> {{ selectedResume.ai_provider || 'Deterministic fallback' }}</p>
            <p><span class="font-medium text-slate-900">Model:</span> {{ selectedResume.ai_model || 'N/A' }}</p>
            <p><span class="font-medium text-slate-900">Confidence:</span> {{ selectedResume.ai_confidence_score ?? 0 }}%</p>
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
import Tag from 'primevue/tag'
import { RouterLink } from 'vue-router'

import type { TailoredResume } from '@/modules/jobs/types'
import { listResumes } from '@/modules/resumes/services/resumesApi'
import EmptyState from '@/shared/components/EmptyState.vue'
import ErrorState from '@/shared/components/ErrorState.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
import SkeletonTable from '@/shared/components/SkeletonTable.vue'
import { useDebouncedValue } from '@/shared/composables/useDebouncedValue'
import { getApiErrorMessage, getCollectionTotal } from '@/shared/utils/api'

const loading = ref(false)
const errorMessage = ref('')
const query = ref('')
const debouncedQuery = useDebouncedValue(query, 250)
const resumes = ref<TailoredResume[]>([])
const totalResumes = ref(0)
const detailsDialogVisible = ref(false)
const selectedResume = ref<TailoredResume | null>(null)

const filteredResumes = computed(() => {
  const search = debouncedQuery.value.trim().toLowerCase()

  return resumes.value.filter((resume) => {
    if (!search) {
      return true
    }

    return [
      resume.job?.title || '',
      resume.candidate_profile?.full_name || '',
      resume.headline || '',
    ].some((value) => value.toLowerCase().includes(search))
  })
})

onMounted(async () => {
  await loadResumes()
})

async function loadResumes(): Promise<void> {
  loading.value = true
  errorMessage.value = ''

  try {
    const collection = await listResumes()
    resumes.value = collection.items
    totalResumes.value = getCollectionTotal(collection)
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error, 'Failed to load resumes.')
  } finally {
    loading.value = false
  }
}

function openDetails(resume: TailoredResume): void {
  selectedResume.value = resume
  detailsDialogVisible.value = true
}

function previewUrl(resume: TailoredResume): string | null {
  return resume.html_url || resume.pdf_url || null
}

function openUrl(url: string): void {
  window.open(url, '_blank', 'noopener,noreferrer')
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
