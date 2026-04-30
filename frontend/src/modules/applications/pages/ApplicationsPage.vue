<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Tracking"
      title="Applications"
      description="Track application status, notes, and related profile/job context after matching and resume generation."
    />

    <ErrorState v-if="pageError" title="Applications unavailable" :message="pageError">
      <template #actions>
        <Button label="Retry" icon="pi pi-refresh" @click="loadApplications" />
      </template>
    </ErrorState>

    <SkeletonTable v-if="loading" :columns="6" />

    <div v-else class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-1 flex-col gap-3 md:flex-row">
          <IconField class="w-full md:max-w-sm">
            <InputIcon class="pi pi-search" />
            <InputText v-model.trim="query" fluid placeholder="Search by job, company, or candidate" />
          </IconField>

          <Select
            v-model="statusFilter"
            :options="statusOptionsWithAll"
            option-label="label"
            option-value="value"
            placeholder="Filter by status"
            class="w-full md:w-56"
          />
        </div>

        <div class="flex flex-col gap-3 md:flex-row md:items-center">
          <SelectButton
            v-model="viewMode"
            :options="viewOptions"
            option-label="label"
            option-value="value"
            aria-label="Applications view"
          />
          <Button label="New Application" icon="pi pi-plus" @click="openCreateDialog" />
        </div>
      </div>

      <EmptyState
        v-if="filteredApplications.length === 0"
        title="No applications tracked yet"
        description="Create an application manually here, or generate a resume from the Jobs page and create one from there."
        icon="pi-send"
      >
        <template #actions>
          <Button label="Create Application" icon="pi pi-plus" @click="openCreateDialog" />
          <Button label="Go to Jobs" icon="pi pi-briefcase" severity="secondary" outlined @click="goToJobs" />
        </template>
      </EmptyState>

      <DataTable
        v-else-if="viewMode === 'table'"
        :value="filteredApplications"
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
              <p class="font-medium text-slate-900">{{ data.candidate_profile?.full_name || `Profile #${profileIdFor(data)}` }}</p>
              <p class="text-sm text-slate-500">{{ data.candidate_profile?.headline || 'Candidate profile' }}</p>
            </div>
          </template>
        </Column>

        <Column header="Status">
          <template #body="{ data }">
            <Select
              :model-value="data.status"
              :options="statusOptions"
              option-label="label"
              option-value="value"
              class="w-48"
              :loading="statusUpdatingId === data.id"
              @update:model-value="(value) => handleQuickStatusUpdate(data.id, value)"
            >
              <template #value="{ value }">
                <StatusTag v-if="value" :value="value" />
                <span v-else class="text-slate-400">Select status</span>
              </template>
              <template #option="{ option }">
                <StatusTag :value="option.value" :label="option.label" />
              </template>
            </Select>
          </template>
        </Column>

        <Column header="Resume">
          <template #body="{ data }">
            <div class="space-y-1">
              <p class="text-sm font-medium text-slate-900">{{ data.resume?.headline || resumeLabel(data) }}</p>
              <Button
                v-if="resumePreviewUrl(data)"
                label="Open"
                icon="pi pi-external-link"
                size="small"
                text
                @click="openUrl(resumePreviewUrl(data)!)"
              />
            </div>
          </template>
        </Column>

        <Column field="created_at" header="Created">
          <template #body="{ data }">
            {{ formatDateTime(data.created_at) }}
          </template>
        </Column>

        <Column header="Actions" :style="{ width: '17rem' }">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-2">
              <Button label="View" icon="pi pi-eye" size="small" text @click="openDetailsDialog(data.id)" />
              <Button label="Edit" icon="pi pi-pencil" size="small" severity="secondary" outlined @click="openEditDialog(data.id)" />
              <Button label="Delete" icon="pi pi-trash" size="small" severity="danger" text @click="confirmDelete(data)" />
            </div>
          </template>
        </Column>
      </DataTable>

      <div v-else class="mt-6 overflow-x-auto pb-2">
        <div class="flex min-w-max gap-4">
          <div
            v-for="column in kanbanColumns"
            :key="column.value"
            class="w-80 shrink-0 rounded-3xl border border-slate-200 bg-slate-50 p-4"
            @dragover.prevent
            @drop="handleDropOnStatus(column.value)"
          >
            <div class="mb-4 flex items-center justify-between gap-3">
              <div class="space-y-1">
                <h3 class="text-sm font-semibold text-slate-900">{{ column.label }}</h3>
                <p class="text-xs text-slate-500">{{ applicationsByStatus[column.value]?.length || 0 }} applications</p>
              </div>
              <StatusTag :value="column.value" :label="column.label" />
            </div>

            <div v-if="(applicationsByStatus[column.value]?.length || 0) === 0" class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-5 text-sm text-slate-500">
              No applications in this stage.
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="application in applicationsByStatus[column.value]"
                :key="application.id"
                draggable="true"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300"
                :class="{ 'opacity-60': statusUpdatingId === application.id }"
                @dragstart="handleDragStart(application.id)"
                @dragend="handleDragEnd"
              >
                <div class="space-y-3">
                  <div>
                    <p class="font-medium text-slate-900">{{ application.job?.title || `Job #${application.job_id}` }}</p>
                    <p class="text-sm text-slate-500">{{ application.job?.company_name || 'Unknown company' }}</p>
                  </div>

                  <div class="text-sm text-slate-600">
                    <p>{{ application.candidate_profile?.full_name || `Profile #${profileIdFor(application)}` }}</p>
                    <p class="text-xs text-slate-400">{{ application.candidate_profile?.headline || 'Candidate profile' }}</p>
                  </div>

                  <div class="flex flex-wrap gap-2">
                    <StatusTag :value="application.status" />
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-500">
                      {{ formatDateTime(application.created_at) }}
                    </span>
                  </div>

                  <div v-if="application.notes" class="line-clamp-3 text-sm leading-6 text-slate-600">
                    {{ application.notes }}
                  </div>

                  <div class="flex flex-wrap gap-2">
                    <Button label="View" icon="pi pi-eye" size="small" text @click="openDetailsDialog(application.id)" />
                    <Button label="Edit" icon="pi pi-pencil" size="small" severity="secondary" outlined @click="openEditDialog(application.id)" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <Dialog v-model:visible="formDialogVisible" modal :header="formDialogTitle" :style="{ width: '42rem' }">
      <form class="space-y-4" @submit.prevent="submitForm">
        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700">Job</label>
          <Select
            v-model="form.job_id"
            :options="jobs"
            option-label="title"
            option-value="id"
            fluid
            filter
            placeholder="Select a job"
          >
            <template #option="{ option }">
              <div>
                <p class="font-medium text-slate-900">{{ option.title }}</p>
                <p class="text-sm text-slate-500">{{ option.company_name || 'Unknown company' }}</p>
              </div>
            </template>
          </Select>
          <FormError :message="fieldError('job_id')" />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700">Candidate Profile</label>
          <Select
            v-model="form.profile_id"
            :options="profiles"
            option-label="headline"
            option-value="id"
            fluid
            filter
            placeholder="Select a profile"
          >
            <template #option="{ option }">
              <div>
                <p class="font-medium text-slate-900">{{ option.headline || option.full_name }}</p>
                <p class="text-sm text-slate-500">{{ option.full_name }}</p>
              </div>
            </template>
          </Select>
          <FormError :message="fieldError('profile_id')" />
        </div>

        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
          Resume selection is optional in this screen. If an application was created from a generated resume, its linked resume will still appear in details.
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700">Status</label>
          <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" fluid />
          <FormError :message="fieldError('status')" />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700">Notes</label>
          <Textarea v-model="form.notes" fluid auto-resize rows="5" />
        </div>

        <div v-if="formError" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ formError }}
        </div>

        <div class="flex justify-end gap-3">
          <Button type="button" label="Cancel" severity="secondary" text @click="formDialogVisible = false" />
          <LoadingButton type="submit" :loading="saving" :label="editingApplicationId ? 'Save Application' : 'Create Application'" loading-label="Saving..." />
        </div>
      </form>
    </Dialog>

    <Dialog v-model:visible="detailsDialogVisible" modal header="Application Details" :style="{ width: '58rem' }">
      <div v-if="detailsLoading" class="flex items-center justify-center py-16">
        <ProgressSpinner stroke-width="4" />
      </div>
        <div v-else-if="selectedApplication" class="space-y-6">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <h3 class="text-2xl font-semibold text-slate-900">{{ selectedApplication.job?.title || `Job #${selectedApplication.job_id}` }}</h3>
              <p class="mt-1 text-sm text-slate-500">{{ selectedApplication.job?.company_name || 'Unknown company' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <StatusTag :value="selectedApplication.status" />
              <Button label="Log Activity" icon="pi pi-history" size="small" severity="secondary" outlined @click="openEventDialog" />
            </div>
          </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Candidate</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedApplication.candidate_profile?.full_name || `Profile #${profileIdFor(selectedApplication)}` }}</p>
            <p class="text-sm text-slate-500">{{ selectedApplication.candidate_profile?.headline || '' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Created</p>
            <p class="mt-1 font-medium text-slate-900">{{ formatDateTime(selectedApplication.created_at) }}</p>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h4 class="text-lg font-semibold text-slate-900">Job URL</h4>
            <Button v-if="selectedApplication.job?.url" label="Open URL" icon="pi pi-external-link" size="small" text @click="openUrl(selectedApplication.job.url)" />
          </div>
          <p class="break-all text-sm text-sky-700">{{ selectedApplication.job?.url || 'N/A' }}</p>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h4 class="text-lg font-semibold text-slate-900">Resume</h4>
            <Button
              v-if="resumePreviewUrl(selectedApplication)"
              label="Open Preview"
              icon="pi pi-external-link"
              size="small"
              text
              @click="openUrl(resumePreviewUrl(selectedApplication)!)"
            />
          </div>
          <p class="text-sm text-slate-700">{{ selectedApplication.resume?.headline || resumeLabel(selectedApplication) }}</p>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">Notes</h4>
          <p class="text-sm leading-6 text-slate-700">{{ selectedApplication.notes || 'No notes available.' }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Applied At</p>
            <p class="mt-1 font-medium text-slate-900">{{ formatDateTime(selectedApplication.applied_at) }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Follow Up</p>
            <p class="mt-1 font-medium text-slate-900">{{ formatDate(selectedApplication.follow_up_date) }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Interview</p>
            <p class="mt-1 font-medium text-slate-900">{{ formatDateTime(selectedApplication.interview_date) }}</p>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <div class="mb-4 flex items-center justify-between gap-3">
            <h4 class="text-lg font-semibold text-slate-900">Timeline</h4>
            <Button label="Add Activity" icon="pi pi-plus" size="small" text @click="openEventDialog" />
          </div>

          <div v-if="(selectedApplication.events?.length || 0) === 0" class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-4 text-sm text-slate-500">
            No timeline activity has been logged yet.
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="event in selectedApplication.events || []"
              :key="event.id"
              class="rounded-2xl border border-slate-200 bg-white px-4 py-4"
            >
              <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                  <div class="flex flex-wrap items-center gap-2">
                    <StatusTag :value="event.type" :label="eventTypeLabel(event.type)" />
                    <span class="text-xs text-slate-500">{{ formatDateTime(event.occurred_at || event.created_at) }}</span>
                  </div>
                  <p v-if="event.note" class="text-sm leading-6 text-slate-700">{{ event.note }}</p>
                </div>
              </div>
              <div
                v-if="event.metadata && Object.keys(event.metadata).length > 0"
                class="mt-3 rounded-2xl bg-slate-50 px-3 py-3 text-xs text-slate-600"
              >
                <pre class="overflow-auto whitespace-pre-wrap break-words">{{ JSON.stringify(event.metadata, null, 2) }}</pre>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Dialog>

    <Dialog v-model:visible="eventDialogVisible" modal header="Log Application Activity" :style="{ width: '36rem' }">
      <form class="space-y-4" @submit.prevent="submitEvent">
        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700">Activity Type</label>
          <Select v-model="eventForm.type" :options="eventTypeOptions" option-label="label" option-value="value" fluid />
          <FormError :message="eventFieldError('type')" />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700">Note</label>
          <Textarea v-model="eventForm.note" fluid auto-resize rows="4" />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700">Metadata JSON</label>
          <Textarea v-model="eventForm.metadata_json" fluid auto-resize rows="5" placeholder='{"follow_up_date":"2026-05-07","interview_date":"2026-05-10T14:00:00Z"}' />
          <FormError :message="eventFieldError('metadata')" />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700">Occurred At</label>
          <InputText v-model="eventForm.occurred_at" type="datetime-local" fluid />
        </div>

        <div v-if="eventFormError" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ eventFormError }}
        </div>

        <div class="flex justify-end gap-3">
          <Button type="button" label="Cancel" severity="secondary" text @click="eventDialogVisible = false" />
          <LoadingButton type="submit" :loading="eventSaving" label="Log Activity" loading-label="Saving..." />
        </div>
      </form>
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
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { useRouter } from 'vue-router'

import type { CandidateProfile } from '@/modules/candidate-profile/types'
import type { Job } from '@/modules/jobs/types'
import type { Application, ApplicationEventPayload, ApplicationEventType, ApplicationPayload, ApplicationStatus } from '@/modules/applications/types'
import { listProfiles } from '@/modules/candidate-profile/services/candidateProfilesApi'
import { listJobs } from '@/modules/jobs/services/jobsApi'
import {
  createApplication,
  createApplicationEvent,
  deleteApplication,
  getApplication,
  listApplications,
  updateApplication,
} from '@/modules/applications/services/applicationsApi'
import EmptyState from '@/shared/components/EmptyState.vue'
import ErrorState from '@/shared/components/ErrorState.vue'
import FormError from '@/shared/components/FormError.vue'
import LoadingButton from '@/shared/components/LoadingButton.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
import SkeletonTable from '@/shared/components/SkeletonTable.vue'
import StatusTag from '@/shared/components/StatusTag.vue'
import { useDebouncedValue } from '@/shared/composables/useDebouncedValue'
import { getApiErrorMessage, getApiValidationErrors } from '@/shared/utils/api'

interface ApplicationFormState {
  job_id: number | null
  profile_id: number | null
  status: ApplicationStatus
  notes: string
}

interface ApplicationEventFormState {
  type: ApplicationEventType
  note: string
  metadata_json: string
  occurred_at: string
}

const toast = useToast()
const confirm = useConfirm()
const router = useRouter()

const statusOptions: Array<{ label: string; value: ApplicationStatus }> = [
  { label: 'Draft', value: 'draft' },
  { label: 'Ready To Apply', value: 'ready_to_apply' },
  { label: 'Applied', value: 'applied' },
  { label: 'Interviewing', value: 'interviewing' },
  { label: 'Rejected', value: 'rejected' },
  { label: 'Offer', value: 'offer' },
  { label: 'Archived', value: 'archived' },
]

const statusOptionsWithAll = [{ label: 'All Statuses', value: 'all' as const }, ...statusOptions]
const viewOptions = [
  { label: 'Table', value: 'table' as const },
  { label: 'Board', value: 'board' as const },
]
const eventTypeOptions: Array<{ label: string; value: ApplicationEventType }> = [
  { label: 'Applied Manually', value: 'applied_manually' },
  { label: 'Interview Scheduled', value: 'interview_scheduled' },
  { label: 'Follow-Up Scheduled', value: 'follow_up_scheduled' },
  { label: 'Follow-Up Sent', value: 'follow_up_sent' },
  { label: 'Response Received', value: 'response_received' },
  { label: 'Offer Received', value: 'offer_received' },
  { label: 'Rejected', value: 'rejected' },
  { label: 'Archived', value: 'archived' },
  { label: 'Note Added', value: 'note_added' },
]

const loading = ref(false)
const saving = ref(false)
const detailsLoading = ref(false)
const eventSaving = ref(false)
const pageError = ref('')
const formError = ref('')
const eventFormError = ref('')
const query = ref('')
const debouncedQuery = useDebouncedValue(query, 250)
const statusFilter = ref<'all' | ApplicationStatus>('all')
const viewMode = ref<'table' | 'board'>('table')
const applications = ref<Application[]>([])
const jobs = ref<Job[]>([])
const profiles = ref<CandidateProfile[]>([])
const selectedApplication = ref<Application | null>(null)
const formDialogVisible = ref(false)
const detailsDialogVisible = ref(false)
const eventDialogVisible = ref(false)
const editingApplicationId = ref<number | null>(null)
const statusUpdatingId = ref<number | null>(null)
const draggingApplicationId = ref<number | null>(null)
const validationErrors = ref<Record<string, string[]>>({})
const eventValidationErrors = ref<Record<string, string[]>>({})

const form = reactive<ApplicationFormState>({
  job_id: null,
  profile_id: null,
  status: 'draft',
  notes: '',
})

const eventForm = reactive<ApplicationEventFormState>({
  type: 'note_added',
  note: '',
  metadata_json: '',
  occurred_at: '',
})

const filteredApplications = computed(() => {
  const search = debouncedQuery.value.trim().toLowerCase()

  return applications.value.filter((application) => {
    const matchesStatus = statusFilter.value === 'all' || application.status === statusFilter.value
    const matchesSearch =
      search.length === 0 ||
      [
        application.job?.title || '',
        application.job?.company_name || '',
        application.candidate_profile?.full_name || '',
      ].some((value) => value.toLowerCase().includes(search))

    return matchesStatus && matchesSearch
  })
})

const kanbanColumns = computed(() => statusOptions)

const applicationsByStatus = computed<Record<ApplicationStatus, Application[]>>(() => {
  const grouped = Object.fromEntries(
    statusOptions.map((status) => [status.value, [] as Application[]]),
  ) as Record<ApplicationStatus, Application[]>

  for (const application of filteredApplications.value) {
    grouped[application.status]?.push(application)
  }

  return grouped
})

const formDialogTitle = computed(() => (editingApplicationId.value ? 'Edit Application' : 'Create Application'))

onMounted(async () => {
  await Promise.all([loadApplications(), loadReferenceData()])
})

async function loadApplications(): Promise<void> {
  loading.value = true
  pageError.value = ''

  try {
    const collection = await listApplications()
    applications.value = collection.items
  } catch (error) {
    pageError.value = getApiErrorMessage(error, 'Failed to load applications.')
  } finally {
    loading.value = false
  }
}

async function loadReferenceData(): Promise<void> {
  try {
    const [jobsCollection, profilesCollection] = await Promise.all([listJobs(), listProfiles()])
    jobs.value = jobsCollection.items
    profiles.value = profilesCollection.items
  } catch {
    // Keep the page usable even if one reference list fails.
  }
}

function openCreateDialog(): void {
  editingApplicationId.value = null
  resetForm()
  formDialogVisible.value = true
}

async function openEditDialog(id: number): Promise<void> {
  try {
    const application = await getApplication(id)
    editingApplicationId.value = id
    form.job_id = application.job_id
    form.profile_id = profileIdFor(application)
    form.status = application.status
    form.notes = application.notes || ''
    validationErrors.value = {}
    formError.value = ''
    formDialogVisible.value = true
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Load failed', detail: getApiErrorMessage(error, 'Failed to load application.'), life: 4000 })
  }
}

async function openDetailsDialog(id: number): Promise<void> {
  detailsDialogVisible.value = true
  detailsLoading.value = true

  try {
    selectedApplication.value = await getApplication(id)
    upsertApplication(selectedApplication.value)
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Load failed', detail: getApiErrorMessage(error, 'Failed to load application details.'), life: 4000 })
    detailsDialogVisible.value = false
  } finally {
    detailsLoading.value = false
  }
}

function openEventDialog(): void {
  eventDialogVisible.value = true
  eventForm.type = 'note_added'
  eventForm.note = ''
  eventForm.metadata_json = ''
  eventForm.occurred_at = ''
  eventValidationErrors.value = {}
  eventFormError.value = ''
}

async function submitForm(): Promise<void> {
  validationErrors.value = {}
  formError.value = ''

  const payload = buildPayload()
  const clientErrors: Record<string, string[]> = {}

  if (!payload.job_id) {
    clientErrors.job_id = ['Job is required.']
  }
  if (!payload.profile_id) {
    clientErrors.profile_id = ['Candidate profile is required.']
  }
  if (!payload.status) {
    clientErrors.status = ['Status is required.']
  }

  if (Object.keys(clientErrors).length > 0) {
    validationErrors.value = clientErrors
    return
  }

  saving.value = true

  try {
    const application = editingApplicationId.value
      ? await updateApplication(editingApplicationId.value, payload)
      : await createApplication(payload)

    upsertApplication(application)
    formDialogVisible.value = false
    toast.add({
      severity: 'success',
      summary: editingApplicationId.value ? 'Application updated' : 'Application created',
      detail: editingApplicationId.value ? 'Application changes saved.' : 'Application created successfully.',
      life: 3000,
    })
  } catch (error) {
    validationErrors.value = getApiValidationErrors(error)
    formError.value = getApiErrorMessage(error, 'Failed to save application.')
  } finally {
    saving.value = false
  }
}

function confirmDelete(application: Application): void {
  confirm.require({
    header: 'Delete application',
    message: `Delete application for "${application.job?.title || `Job #${application.job_id}`}"? This cannot be undone.`,
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await deleteApplication(application.id)
        applications.value = applications.value.filter((item) => item.id !== application.id)
        toast.add({ severity: 'success', summary: 'Application deleted', detail: 'Application removed.', life: 3000 })
      } catch (error) {
        toast.add({ severity: 'error', summary: 'Delete failed', detail: getApiErrorMessage(error, 'Failed to delete application.'), life: 4000 })
      }
    },
  })
}

async function handleQuickStatusUpdate(id: number, value: ApplicationStatus): Promise<void> {
  statusUpdatingId.value = id

  try {
    const application = await updateApplication(id, { status: value })
    upsertApplication(application)
    toast.add({ severity: 'success', summary: 'Status updated', detail: `Application moved to ${statusLabel(value)}.`, life: 2500 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Status update failed', detail: getApiErrorMessage(error, 'Failed to update status.'), life: 4000 })
  } finally {
    statusUpdatingId.value = null
  }
}

function handleDragStart(id: number): void {
  draggingApplicationId.value = id
}

function handleDragEnd(): void {
  draggingApplicationId.value = null
}

async function handleDropOnStatus(status: ApplicationStatus): Promise<void> {
  const applicationId = draggingApplicationId.value

  if (!applicationId) {
    return
  }

  const application = applications.value.find((item) => item.id === applicationId)
  draggingApplicationId.value = null

  if (!application || application.status === status) {
    return
  }

  await handleQuickStatusUpdate(applicationId, status)
}

async function submitEvent(): Promise<void> {
  if (!selectedApplication.value) {
    return
  }

  eventValidationErrors.value = {}
  eventFormError.value = ''

  const payload: ApplicationEventPayload = {
    type: eventForm.type,
    note: eventForm.note.trim() || null,
    metadata: null,
    occurred_at: eventForm.occurred_at ? new Date(eventForm.occurred_at).toISOString() : null,
  }

  if (eventForm.metadata_json.trim() !== '') {
    try {
      payload.metadata = JSON.parse(eventForm.metadata_json) as Record<string, unknown>
    } catch {
      eventValidationErrors.value = { metadata: ['Metadata must be valid JSON.'] }
      return
    }
  }

  eventSaving.value = true

  try {
    await createApplicationEvent(selectedApplication.value.id, payload)
    selectedApplication.value = await getApplication(selectedApplication.value.id)
    upsertApplication(selectedApplication.value)
    eventDialogVisible.value = false
    toast.add({
      severity: 'success',
      summary: 'Activity logged',
      detail: 'Application timeline updated successfully.',
      life: 3000,
    })
  } catch (error) {
    eventValidationErrors.value = getApiValidationErrors(error)
    eventFormError.value = getApiErrorMessage(error, 'Failed to log activity.')
  } finally {
    eventSaving.value = false
  }
}

function buildPayload(): ApplicationPayload {
  return {
    job_id: Number(form.job_id),
    profile_id: Number(form.profile_id),
    candidate_profile_id: Number(form.profile_id),
    status: form.status,
    notes: form.notes.trim() || null,
  }
}

function upsertApplication(application: Application): void {
  const index = applications.value.findIndex((item) => item.id === application.id)
  if (index === -1) {
    applications.value.unshift(application)
    return
  }

  applications.value.splice(index, 1, application)
}

function resetForm(): void {
  form.job_id = null
  form.profile_id = null
  form.status = 'draft'
  form.notes = ''
  validationErrors.value = {}
  formError.value = ''
}

function fieldError(field: string): string | null {
  return validationErrors.value[field]?.[0] ?? null
}

function eventFieldError(field: string): string | null {
  return eventValidationErrors.value[field]?.[0] ?? null
}

function statusLabel(status: string): string {
  return status.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}

function eventTypeLabel(type: string): string {
  return type.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}

function profileIdFor(application: Application): number {
  return application.candidate_profile_id ?? application.profile_id ?? 0
}

function resumeLabel(application: Application): string {
  const id = application.resume_id ?? application.tailored_resume_id
  return id ? `Resume #${id}` : 'No linked resume'
}

function resumePreviewUrl(application: Application): string | null {
  if (!application.resume?.html_path) {
    return null
  }

  const apiBase = new URL(import.meta.env.VITE_API_BASE_URL)
  return `${apiBase.origin}/storage/${application.resume.html_path}`
}

function openUrl(url: string): void {
  window.open(url, '_blank', 'noopener,noreferrer')
}

function goToJobs(): void {
  void router.push('/jobs')
}

function formatDate(value?: string | null): string {
  if (!value) {
    return 'N/A'
  }

  return new Intl.DateTimeFormat('en-US', {
    dateStyle: 'medium',
  }).format(new Date(value))
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
