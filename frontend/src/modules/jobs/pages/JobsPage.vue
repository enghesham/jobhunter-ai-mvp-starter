<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Job Management"
      title="Jobs"
      description="Review ingested jobs, inspect descriptions, and verify normalized payload quality before analysis and matching."
    />

    <div v-if="errorMessage" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ errorMessage }}
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <IconField class="w-full lg:max-w-sm">
          <InputIcon class="pi pi-search" />
          <InputText v-model.trim="query" fluid placeholder="Search by title, company, or location" />
        </IconField>

        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
          Showing {{ filteredJobs.length }} of {{ totalJobs }} jobs
        </div>
      </div>

      <DataTable
        :value="filteredJobs"
        :loading="loading"
        data-key="id"
        paginator
        :rows="10"
        class="mt-6"
        striped-rows
        responsive-layout="scroll"
        empty-message="No jobs available."
      >
        <Column field="title" header="Title">
          <template #body="{ data }">
            <div>
              <p class="font-medium text-slate-900">{{ data.title }}</p>
              <p class="text-sm text-slate-500">{{ data.company_name || 'Unknown company' }}</p>
            </div>
          </template>
        </Column>

        <Column field="location" header="Location">
          <template #body="{ data }">
            <div class="space-y-1">
              <p>{{ data.location || '—' }}</p>
              <Tag :severity="data.is_remote ? 'success' : 'contrast'" :value="data.is_remote ? 'Remote' : 'On-site'" />
            </div>
          </template>
        </Column>

        <Column field="status" header="Status">
          <template #body="{ data }">
            <Tag :severity="statusSeverity(data.status)" :value="data.status || 'unknown'" />
          </template>
        </Column>

        <Column field="created_at" header="Created">
          <template #body="{ data }">
            {{ formatDateTime(data.created_at) }}
          </template>
        </Column>

        <Column header="Actions" :style="{ width: '15rem' }">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-2">
              <Button
                v-if="data.url"
                label="Open URL"
                icon="pi pi-external-link"
                size="small"
                text
                @click="openUrl(data.url)"
              />
              <Button
                label="Description"
                icon="pi pi-align-left"
                size="small"
                severity="secondary"
                outlined
                @click="openDescription(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Dialog
      v-model:visible="descriptionDialogVisible"
      modal
      header="Job Description"
      :style="{ width: '56rem' }"
    >
      <div class="space-y-4">
        <div>
          <h3 class="text-xl font-semibold text-slate-900">{{ selectedJob?.title }}</h3>
          <p class="text-sm text-slate-500">{{ selectedJob?.company_name || 'Unknown company' }}</p>
        </div>

        <div class="grid gap-3 text-sm md:grid-cols-3">
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Location</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedJob?.location || '—' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Status</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedJob?.status || 'unknown' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Remote</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedJob?.is_remote ? 'Yes' : 'No' }}</p>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <pre class="whitespace-pre-wrap break-words font-sans text-sm leading-6 text-slate-700">{{ descriptionText }}</pre>
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

import { listJobs } from '@/modules/jobs/services/jobsApi'
import type { Job } from '@/modules/jobs/types'
import PageHeader from '@/shared/components/PageHeader.vue'
import { getApiErrorMessage, getCollectionTotal } from '@/shared/utils/api'

const loading = ref(false)
const errorMessage = ref('')
const query = ref('')
const jobs = ref<Job[]>([])
const totalJobs = ref(0)
const descriptionDialogVisible = ref(false)
const selectedJob = ref<Job | null>(null)

const filteredJobs = computed(() => {
  const search = query.value.trim().toLowerCase()

  return jobs.value.filter((job) => {
    if (!search) {
      return true
    }

    return [job.title, job.company_name ?? '', job.location ?? '']
      .some((value) => value.toLowerCase().includes(search))
  })
})

const descriptionText = computed(() => selectedJob.value?.description_clean || selectedJob.value?.description_raw || 'No description available.')

onMounted(async () => {
  await loadJobs()
})

async function loadJobs(): Promise<void> {
  loading.value = true
  errorMessage.value = ''

  try {
    const collection = await listJobs()
    jobs.value = collection.items
    totalJobs.value = getCollectionTotal(collection)
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error, 'Failed to load jobs.')
  } finally {
    loading.value = false
  }
}

function openDescription(job: Job): void {
  selectedJob.value = job
  descriptionDialogVisible.value = true
}

function openUrl(url: string): void {
  window.open(url, '_blank', 'noopener,noreferrer')
}

function formatDateTime(value?: string | null): string {
  if (!value) {
    return '—'
  }

  return new Intl.DateTimeFormat('en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function statusSeverity(status?: string | null): 'contrast' | 'success' | 'warn' | 'info' {
  switch (status) {
    case 'matched':
      return 'success'
    case 'analyzed':
      return 'info'
    case 'archived':
      return 'contrast'
    default:
      return 'warn'
  }
}
</script>
