<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Job Management"
      title="Job Sources"
      description="Manage ingestion sources and push manual job payloads into the backend safely."
    />

    <ErrorState v-if="pageError" title="Job sources unavailable" :message="pageError">
      <template #actions>
        <Button label="Retry" icon="pi pi-refresh" @click="loadSources" />
      </template>
    </ErrorState>

    <SkeletonTable v-if="loading" :columns="6" />

    <div v-else class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-1 flex-col gap-3 md:flex-row">
          <IconField class="w-full md:max-w-sm">
            <InputIcon class="pi pi-search" />
            <InputText v-model.trim="sourceQuery" fluid placeholder="Search by name or URL" />
          </IconField>

          <Select
            v-model="filters.type"
            :options="typeFilterOptions"
            option-label="label"
            option-value="value"
            placeholder="Filter by type"
            class="w-full md:w-52"
          />
        </div>

        <Button label="New Source" icon="pi pi-plus" @click="openCreateDialog" />
      </div>

      <EmptyState
        v-if="filteredSources.length === 0"
        title="No job sources yet"
        description="Create an RSS, Greenhouse, or Lever source first. Opportunities can then collect and pre-screen jobs from active sources."
        icon="pi-database"
      >
        <template #actions>
          <Button label="Create Source" icon="pi pi-plus" @click="openCreateDialog" />
        </template>
      </EmptyState>

      <DataTable
        v-else
        :value="filteredSources"
        data-key="id"
        paginator
        :rows="10"
        class="mt-6"
        striped-rows
        responsive-layout="scroll"
      >
        <Column field="name" header="Name">
          <template #body="{ data }">
            <div>
              <p class="font-medium text-slate-900">{{ data.name }}</p>
              <a :href="data.url" target="_blank" rel="noreferrer" class="text-sm text-sky-700 hover:underline">
                {{ data.url }}
              </a>
            </div>
          </template>
        </Column>

        <Column field="type" header="Type">
          <template #body="{ data }">
            <Tag :severity="typeSeverity(data.type)" :value="data.type" />
          </template>
        </Column>

        <Column field="is_active" header="Status">
          <template #body="{ data }">
            <Tag :severity="data.is_active ? 'success' : 'contrast'" :value="data.is_active ? 'Active' : 'Inactive'" />
          </template>
        </Column>

        <Column field="jobs_count" header="Jobs">
          <template #body="{ data }">
            {{ data.jobs_count ?? 0 }}
          </template>
        </Column>

        <Column field="updated_at" header="Updated">
          <template #body="{ data }">
            {{ formatDateTime(data.updated_at) }}
          </template>
        </Column>

        <Column header="Actions" :style="{ width: '17rem' }">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-2">
              <Button label="Ingest Jobs" icon="pi pi-upload" size="small" severity="secondary" @click="openIngestDialog(data)" />
              <Button label="Edit" icon="pi pi-pencil" size="small" text @click="openEditDialog(data)" />
              <Button label="Delete" icon="pi pi-trash" size="small" text severity="danger" @click="confirmDelete(data)" />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Dialog v-model:visible="sourceDialogVisible" modal :header="dialogTitle" :style="{ width: '42rem' }">
      <form class="space-y-4" @submit.prevent="submitSourceForm">
        <div class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700" for="source-name">Name</label>
            <InputText id="source-name" v-model.trim="sourceForm.name" fluid />
            <FormError :message="fieldError('name')" />
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700" for="source-type">Type</label>
            <Select
              id="source-type"
              v-model="sourceForm.type"
              :options="sourceTypeOptions"
              option-label="label"
              option-value="value"
              fluid
            />
            <FormError :message="fieldError('type')" />
          </div>
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700" for="source-url">URL</label>
          <InputText id="source-url" v-model.trim="sourceForm.url" fluid />
          <p class="text-xs text-slate-500">{{ sourceUrlHelp }}</p>
          <FormError :message="fieldError('url')" />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700" for="source-config">Config JSON</label>
          <Textarea id="source-config" v-model="sourceForm.configJson" fluid auto-resize rows="8" />
          <p class="text-xs text-slate-500">Optional JSON object that maps to the backend `config` field.</p>
          <FormError :message="fieldError('config')" />
        </div>

        <div class="flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3">
          <ToggleSwitch v-model="sourceForm.is_active" input-id="source-active" />
          <label for="source-active" class="text-sm font-medium text-slate-700">Source is active</label>
        </div>

        <div v-if="formError" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ formError }}
        </div>

        <div class="flex justify-end gap-3">
          <Button type="button" label="Cancel" severity="secondary" text @click="sourceDialogVisible = false" />
          <LoadingButton
            type="submit"
            :loading="savingSource"
            :label="editingSourceId ? 'Save Changes' : 'Create Source'"
            loading-label="Saving..."
          />
        </div>
      </form>
    </Dialog>

    <Dialog
      v-model:visible="ingestDialogVisible"
      modal
      header="Manual Job Ingestion"
      :style="{ width: '72rem' }"
    >
      <div class="space-y-4">
        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
          Target source:
          <span class="font-semibold text-slate-900">{{ selectedSource?.name ?? 'Unknown' }}</span>
        </div>

        <div v-for="(job, index) in ingestionRows" :key="job.key" class="rounded-3xl border border-slate-200 p-4">
          <div class="mb-4 flex items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">Job {{ index + 1 }}</h3>
              <p class="text-sm text-slate-500">Provide the minimal normalized payload expected by the backend ingestion endpoint.</p>
            </div>

            <Button
              v-if="ingestionRows.length > 1"
              icon="pi pi-trash"
              severity="danger"
              text
              @click="removeIngestionRow(index)"
            />
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700">External ID</label>
              <InputText v-model.trim="job.external_id" fluid />
            </div>

            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700">Status</label>
              <Select
                v-model="job.status"
                :options="jobStatusOptions"
                option-label="label"
                option-value="value"
                fluid
              />
            </div>

            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700">Title</label>
              <InputText v-model.trim="job.title" fluid />
              <FormError :message="jobFieldError(index, 'title')" />
            </div>

            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700">Company</label>
              <InputText v-model.trim="job.company" fluid />
              <FormError :message="jobFieldError(index, 'company')" />
            </div>

            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700">Location</label>
              <InputText v-model.trim="job.location" fluid />
            </div>

            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700">Job URL</label>
              <InputText v-model.trim="job.url" fluid />
              <FormError :message="jobFieldError(index, 'url')" />
            </div>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_14rem]">
            <div class="space-y-2">
              <label class="text-sm font-medium text-slate-700">Description</label>
              <Textarea v-model="job.description" fluid auto-resize rows="7" />
              <FormError :message="jobFieldError(index, 'description')" />
            </div>

            <div class="space-y-4">
              <div class="rounded-2xl bg-slate-50 px-4 py-3">
                <label class="mb-3 block text-sm font-medium text-slate-700">Remote</label>
                <ToggleSwitch v-model="job.is_remote" />
              </div>

              <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Raw Payload JSON</label>
                <Textarea v-model="job.raw_payload_json" fluid auto-resize rows="7" />
                <FormError :message="jobFieldError(index, 'raw_payload')" />
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap justify-between gap-3">
          <Button label="Add Another Job" icon="pi pi-plus" severity="secondary" outlined @click="addIngestionRow" />

          <div class="flex gap-3">
            <Button type="button" label="Cancel" severity="secondary" text @click="ingestDialogVisible = false" />
            <LoadingButton
              :loading="submittingIngestion"
              label="Submit Ingestion"
              loading-label="Submitting..."
              @click="submitIngestion"
            />
          </div>
        </div>

        <div v-if="ingestionError" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ ingestionError }}
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

import {
  createJobSource,
  deleteJobSource,
  ingestJobs,
  listJobSources,
  updateJobSource,
} from '@/modules/job-sources/services/jobSourcesApi'
import type { IngestionJobInput, JobSource, JobSourcePayload } from '@/modules/job-sources/types'
import EmptyState from '@/shared/components/EmptyState.vue'
import ErrorState from '@/shared/components/ErrorState.vue'
import FormError from '@/shared/components/FormError.vue'
import LoadingButton from '@/shared/components/LoadingButton.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
import SkeletonTable from '@/shared/components/SkeletonTable.vue'
import { useDebouncedValue } from '@/shared/composables/useDebouncedValue'
import { getApiErrorMessage, getApiValidationErrors } from '@/shared/utils/api'

interface SourceFormState {
  name: string
  type: string
  url: string
  is_active: boolean
  configJson: string
}

interface IngestionRowState {
  key: number
  external_id: string
  title: string
  company: string
  location: string
  is_remote: boolean
  url: string
  description: string
  raw_payload_json: string
  status: string
}

const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const savingSource = ref(false)
const submittingIngestion = ref(false)
const pageError = ref('')
const formError = ref('')
const ingestionError = ref('')
const sourceDialogVisible = ref(false)
const ingestDialogVisible = ref(false)
const editingSourceId = ref<number | null>(null)
const selectedSource = ref<JobSource | null>(null)
const validationErrors = ref<Record<string, string[]>>({})
const ingestionValidationErrors = ref<Record<string, string>>({})
const rowKeySeed = ref(1)
const sourceQuery = ref('')
const debouncedSourceQuery = useDebouncedValue(sourceQuery, 250)
const filters = reactive({
  type: 'all',
})
const sources = ref<JobSource[]>([])

const sourceForm = reactive<SourceFormState>(createDefaultSourceForm())
const ingestionRows = ref<IngestionRowState[]>([createIngestionRow()])

const sourceTypeOptions = [
  { label: 'Custom', value: 'custom' },
  { label: 'RSS Feed', value: 'rss' },
  { label: 'Greenhouse', value: 'greenhouse' },
  { label: 'Lever', value: 'lever' },
]

const typeFilterOptions = [
  { label: 'All Types', value: 'all' },
  ...sourceTypeOptions,
]

const jobStatusOptions = [
  { label: 'New', value: 'new' },
  { label: 'Ingested', value: 'ingested' },
  { label: 'Analyzed', value: 'analyzed' },
  { label: 'Matched', value: 'matched' },
  { label: 'Archived', value: 'archived' },
]

const dialogTitle = computed(() => (editingSourceId.value ? 'Edit Job Source' : 'Create Job Source'))
const sourceUrlHelp = computed(() => {
  if (sourceForm.type === 'rss') {
    return 'Use a public RSS/Atom feed URL. The collector reads titles, links, descriptions, and publish dates.'
  }

  if (sourceForm.type === 'greenhouse') {
    return 'Use a Greenhouse board URL or board token URL. Example: https://boards.greenhouse.io/company.'
  }

  if (sourceForm.type === 'lever') {
    return 'Use a Lever postings URL. Example: https://jobs.lever.co/company.'
  }

  return 'Use any URL for manual ingestion sources.'
})

const filteredSources = computed(() => {
  const query = debouncedSourceQuery.value.trim().toLowerCase()

  return sources.value.filter((source) => {
    const matchesQuery =
      query.length === 0 ||
      source.name.toLowerCase().includes(query) ||
      source.url.toLowerCase().includes(query)

    const matchesType = filters.type === 'all' || source.type === filters.type

    return matchesQuery && matchesType
  })
})

onMounted(async () => {
  await loadSources()
})

async function loadSources(): Promise<void> {
  loading.value = true
  pageError.value = ''

  try {
    const collection = await listJobSources()
    sources.value = collection.items
  } catch (error) {
    pageError.value = getApiErrorMessage(error, 'Failed to load job sources.')
  } finally {
    loading.value = false
  }
}

function openCreateDialog(): void {
  editingSourceId.value = null
  resetSourceForm()
  sourceDialogVisible.value = true
}

function openEditDialog(source: JobSource): void {
  editingSourceId.value = source.id
  sourceForm.name = source.name
  sourceForm.type = source.type
  sourceForm.url = source.url
  sourceForm.is_active = source.is_active
  sourceForm.configJson = source.config ? JSON.stringify(source.config, null, 2) : ''
  validationErrors.value = {}
  formError.value = ''
  sourceDialogVisible.value = true
}

async function submitSourceForm(): Promise<void> {
  validationErrors.value = {}
  formError.value = ''

  const parsed = parseSourcePayload()
  if (!parsed.ok) {
    validationErrors.value = parsed.errors
    return
  }

  savingSource.value = true

  try {
    const payload = parsed.payload
    if (editingSourceId.value) {
      await updateJobSource(editingSourceId.value, payload)
      toast.add({ severity: 'success', summary: 'Source updated', detail: 'Job source saved successfully.', life: 3000 })
    } else {
      await createJobSource(payload)
      toast.add({ severity: 'success', summary: 'Source created', detail: 'Job source created successfully.', life: 3000 })
    }

    sourceDialogVisible.value = false
    await loadSources()
  } catch (error) {
    validationErrors.value = getApiValidationErrors(error)
    formError.value = getApiErrorMessage(error, 'Failed to save job source.')
  } finally {
    savingSource.value = false
  }
}

function confirmDelete(source: JobSource): void {
  confirm.require({
    header: 'Delete job source',
    message: `Delete "${source.name}"? This cannot be undone.`,
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await deleteJobSource(source.id)
        toast.add({ severity: 'success', summary: 'Source deleted', detail: 'Job source removed.', life: 3000 })
        await loadSources()
      } catch (error) {
        toast.add({ severity: 'error', summary: 'Delete failed', detail: getApiErrorMessage(error, 'Failed to delete source.'), life: 4000 })
      }
    },
  })
}

function openIngestDialog(source: JobSource): void {
  selectedSource.value = source
  ingestDialogVisible.value = true
  ingestionError.value = ''
  ingestionValidationErrors.value = {}
  ingestionRows.value = [createIngestionRow()]
}

function addIngestionRow(): void {
  ingestionRows.value.push(createIngestionRow())
}

function removeIngestionRow(index: number): void {
  ingestionRows.value.splice(index, 1)
}

async function submitIngestion(): Promise<void> {
  if (!selectedSource.value) {
    return
  }

  ingestionValidationErrors.value = {}
  ingestionError.value = ''

  const parsed = parseIngestionRows()
  if (!parsed.ok) {
    ingestionValidationErrors.value = parsed.errors
    return
  }

  submittingIngestion.value = true

  try {
    const result = await ingestJobs(selectedSource.value.id, parsed.jobs)
    toast.add({
      severity: 'success',
      summary: 'Ingestion completed',
      detail: `Created ${result.created}, updated ${result.updated}, skipped ${result.skipped}.`,
      life: 4500,
    })
    ingestDialogVisible.value = false
    await loadSources()
  } catch (error) {
    ingestionError.value = getApiErrorMessage(error, 'Failed to ingest jobs.')
  } finally {
    submittingIngestion.value = false
  }
}

function parseSourcePayload(): { ok: true; payload: JobSourcePayload } | { ok: false; errors: Record<string, string[]> } {
  const errors: Record<string, string[]> = {}

  if (!sourceForm.name.trim()) {
    errors.name = ['Name is required.']
  }

  if (!sourceForm.type.trim()) {
    errors.type = ['Type is required.']
  }

  if (!sourceForm.url.trim()) {
    errors.url = ['URL is required.']
  } else if (!isValidUrl(sourceForm.url)) {
    errors.url = ['Enter a valid URL.']
  }

  let parsedConfig: Record<string, unknown> | null = null
  if (sourceForm.configJson.trim()) {
    try {
      const value = JSON.parse(sourceForm.configJson) as unknown
      if (typeof value !== 'object' || value === null || Array.isArray(value)) {
        errors.config = ['Config JSON must be an object.']
      } else {
        parsedConfig = value as Record<string, unknown>
      }
    } catch {
      errors.config = ['Config must be valid JSON.']
    }
  }

  if (Object.keys(errors).length > 0) {
    return { ok: false, errors }
  }

  return {
    ok: true,
    payload: {
      name: sourceForm.name.trim(),
      type: sourceForm.type,
      url: sourceForm.url.trim(),
      is_active: sourceForm.is_active,
      config: parsedConfig,
    },
  }
}

function parseIngestionRows(): { ok: true; jobs: IngestionJobInput[] } | { ok: false; errors: Record<string, string> } {
  const errors: Record<string, string> = {}
  const jobs: IngestionJobInput[] = []

  ingestionRows.value.forEach((row, index) => {
    const prefix = `jobs.${index}`

    if (!row.title.trim()) {
      errors[`${prefix}.title`] = 'Title is required.'
    }

    if (!row.company.trim()) {
      errors[`${prefix}.company`] = 'Company is required.'
    }

    if (!row.url.trim()) {
      errors[`${prefix}.url`] = 'URL is required.'
    } else if (!isValidUrl(row.url)) {
      errors[`${prefix}.url`] = 'Enter a valid URL.'
    }

    if (!row.description.trim()) {
      errors[`${prefix}.description`] = 'Description is required.'
    }

    let rawPayload: Record<string, unknown> | null = null
    if (row.raw_payload_json.trim()) {
      try {
        const parsed = JSON.parse(row.raw_payload_json) as unknown
        if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
          errors[`${prefix}.raw_payload`] = 'Raw payload must be a JSON object.'
        } else {
          rawPayload = parsed as Record<string, unknown>
        }
      } catch {
        errors[`${prefix}.raw_payload`] = 'Raw payload must be valid JSON.'
      }
    }

    jobs.push({
      external_id: row.external_id.trim() || null,
      title: row.title.trim(),
      company: row.company.trim(),
      location: row.location.trim() || null,
      is_remote: row.is_remote,
      url: row.url.trim(),
      description: row.description.trim(),
      raw_payload: rawPayload,
      status: row.status,
    })
  })

  if (Object.keys(errors).length > 0) {
    return { ok: false, errors }
  }

  return { ok: true, jobs }
}

function fieldError(field: string): string | null {
  return validationErrors.value[field]?.[0] ?? null
}

function jobFieldError(index: number, field: string): string | null {
  return ingestionValidationErrors.value[`jobs.${index}.${field}`] ?? null
}

function resetSourceForm(): void {
  Object.assign(sourceForm, createDefaultSourceForm())
  validationErrors.value = {}
  formError.value = ''
}

function createDefaultSourceForm(): SourceFormState {
  return {
    name: '',
    type: 'custom',
    url: '',
    is_active: true,
    configJson: '',
  }
}

function createIngestionRow(): IngestionRowState {
  const nextKey = rowKeySeed.value++

  return {
    key: nextKey,
    external_id: '',
    title: '',
    company: '',
    location: '',
    is_remote: true,
    url: '',
    description: '',
    raw_payload_json: '',
    status: 'new',
  }
}

function isValidUrl(value: string): boolean {
  try {
    new URL(value)
    return true
  } catch {
    return false
  }
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

function typeSeverity(type: string): 'contrast' | 'info' | 'success' | 'warn' {
  if (type === 'rss') {
    return 'contrast'
  }

  if (type === 'greenhouse') {
    return 'success'
  }

  if (type === 'lever') {
    return 'info'
  }

  return 'warn'
}
</script>
