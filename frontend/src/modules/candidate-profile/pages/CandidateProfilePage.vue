<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Candidate"
      title="Candidate Profiles"
      description="Manage the profiles used for deterministic job matching and tailored resume generation."
    />

    <ErrorState v-if="pageError" title="Profiles unavailable" :message="pageError">
      <template #actions>
        <Button label="Retry" icon="pi pi-refresh" @click="loadProfiles" />
      </template>
    </ErrorState>

    <SkeletonTable v-if="loading" :columns="5" />

    <div v-else class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <IconField class="w-full lg:max-w-sm">
          <InputIcon class="pi pi-search" />
            <InputText v-model.trim="query" fluid placeholder="Search by full name or headline" />
        </IconField>

        <div class="flex flex-wrap gap-3">
          <Button label="Import Sample Profile" icon="pi pi-upload" severity="secondary" outlined @click="openImportDialog" />
          <Button label="New Profile" icon="pi pi-plus" @click="openCreateDialog" />
        </div>
      </div>

      <EmptyState
        v-if="filteredProfiles.length === 0"
        title="No candidate profiles yet"
        description="Create a profile or import the sample JSON to start matching jobs and generating resumes."
        icon="pi-user"
      >
        <template #actions>
          <Button label="Create Profile" icon="pi pi-plus" @click="openCreateDialog" />
          <Button label="Import Sample" icon="pi pi-upload" severity="secondary" outlined @click="openImportDialog" />
        </template>
      </EmptyState>

      <DataTable
        v-else
        :value="filteredProfiles"
        :loading="loading"
        data-key="id"
        paginator
        :rows="10"
        class="mt-6"
        striped-rows
        responsive-layout="scroll"
        empty-message="No candidate profiles found."
      >
        <Column field="full_name" header="Profile">
          <template #body="{ data }">
            <div>
              <p class="font-medium text-slate-900">{{ data.full_name }}</p>
              <p class="text-sm text-slate-500">{{ data.headline }}</p>
            </div>
          </template>
        </Column>
        <Column field="years_experience" header="Experience">
          <template #body="{ data }">
            {{ data.years_experience }} years
          </template>
        </Column>
        <Column field="core_skills" header="Core Skills">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-2">
              <Tag v-for="skill in (data.core_skills || []).slice(0, 4)" :key="`${data.id}-${skill}`" :value="skill" severity="success" />
              <Tag v-if="(data.core_skills || []).length > 4" :value="`+${data.core_skills.length - 4}`" severity="contrast" />
            </div>
          </template>
        </Column>
        <Column field="updated_at" header="Updated">
          <template #body="{ data }">
            {{ formatDateTime(data.updated_at) }}
          </template>
        </Column>
        <Column header="Actions" :style="{ width: '20rem' }">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-2">
              <Button label="View" icon="pi pi-eye" size="small" text @click="openDetailsDialog(data.id)" />
              <Button label="Edit" icon="pi pi-pencil" size="small" severity="secondary" outlined @click="openEditDialog(data.id)" />
              <Button label="Duplicate" icon="pi pi-copy" size="small" severity="help" text @click="duplicateProfile(data.id)" />
              <Button label="Delete" icon="pi pi-trash" size="small" severity="danger" text @click="confirmDelete(data)" />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Dialog v-model:visible="formDialogVisible" modal :header="formDialogTitle" :style="{ width: '74rem' }">
      <form class="space-y-6" @submit.prevent="submitForm">
        <div class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700">Full Name</label>
            <InputText v-model.trim="form.full_name" fluid />
            <FormError :message="fieldError('full_name')" />
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700">Headline</label>
            <InputText v-model.trim="form.headline" fluid />
            <FormError :message="fieldError('headline')" />
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_14rem]">
          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700">Base Summary</label>
            <Textarea v-model="form.base_summary" fluid auto-resize rows="5" />
            <FormError :message="fieldError('base_summary')" />
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700">Years of Experience</label>
            <InputNumber v-model="form.years_experience" fluid :min="0" :max="60" />
            <FormError :message="fieldError('years_experience')" />
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700">Preferred Roles</label>
            <Chips v-model="form.preferred_roles" fluid separator="," />
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700">Preferred Locations</label>
            <Chips v-model="form.preferred_locations" fluid separator="," />
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700">Preferred Job Types</label>
            <Chips v-model="form.preferred_job_types" fluid separator="," />
          </div>
          <div class="space-y-2">
            <label class="text-sm font-medium text-slate-700">Core Skills</label>
            <Chips v-model="form.core_skills" fluid separator="," />
          </div>
          <div class="space-y-2 lg:col-span-2">
            <label class="text-sm font-medium text-slate-700">Nice to Have Skills</label>
            <Chips v-model="form.nice_to_have_skills" fluid separator="," />
          </div>
        </div>

        <section class="space-y-4">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">Experiences</h3>
              <p class="text-sm text-slate-500">Add the most relevant work history used for matching and resume generation.</p>
            </div>
            <Button label="Add Experience" icon="pi pi-plus" severity="secondary" outlined @click="addExperience" />
          </div>

          <div v-for="(experience, index) in form.experiences" :key="experience.key" class="rounded-3xl border border-slate-200 p-4">
            <div class="mb-4 flex items-center justify-between gap-3">
              <h4 class="text-base font-semibold text-slate-900">Experience {{ index + 1 }}</h4>
              <Button icon="pi pi-trash" severity="danger" text @click="removeExperience(index)" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Company</label>
                <InputText v-model.trim="experience.company" fluid />
                <FormError :message="nestedFieldError(`experiences.${index}.company`)" />
              </div>
              <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Title</label>
                <InputText v-model.trim="experience.title" fluid />
                <FormError :message="nestedFieldError(`experiences.${index}.title`)" />
              </div>
              <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Start Date</label>
                <DatePicker v-model="experience.start_date" fluid date-format="yy-mm-dd" show-icon />
              </div>
              <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">End Date</label>
                <DatePicker v-model="experience.end_date" fluid date-format="yy-mm-dd" show-icon />
              </div>
              <div class="space-y-2 md:col-span-2">
                <label class="text-sm font-medium text-slate-700">Description</label>
                <Textarea v-model="experience.description" fluid auto-resize rows="4" />
                <FormError :message="nestedFieldError(`experiences.${index}.description`)" />
              </div>
            </div>
          </div>
        </section>

        <section class="space-y-4">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">Projects</h3>
              <p class="text-sm text-slate-500">Add representative projects that should influence scoring and tailored resume output.</p>
            </div>
            <Button label="Add Project" icon="pi pi-plus" severity="secondary" outlined @click="addProject" />
          </div>

          <div v-for="(project, index) in form.projects" :key="project.key" class="rounded-3xl border border-slate-200 p-4">
            <div class="mb-4 flex items-center justify-between gap-3">
              <h4 class="text-base font-semibold text-slate-900">Project {{ index + 1 }}</h4>
              <Button icon="pi pi-trash" severity="danger" text @click="removeProject(index)" />
            </div>

            <div class="grid gap-4">
              <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Project Name</label>
                <InputText v-model.trim="project.name" fluid />
                <FormError :message="nestedFieldError(`projects.${index}.name`)" />
              </div>
              <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Description</label>
                <Textarea v-model="project.description" fluid auto-resize rows="4" />
                <FormError :message="nestedFieldError(`projects.${index}.description`)" />
              </div>
              <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">Skills</label>
                <Chips v-model="project.skills" fluid separator="," />
              </div>
            </div>
          </div>
        </section>

        <div v-if="formError" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ formError }}
        </div>

        <div class="flex justify-end gap-3">
          <Button type="button" label="Cancel" severity="secondary" text @click="formDialogVisible = false" />
          <LoadingButton
            type="submit"
            :loading="saving"
            :label="editingProfileId ? 'Save Profile' : 'Create Profile'"
            loading-label="Saving..."
          />
        </div>
      </form>
    </Dialog>

    <Dialog v-model:visible="detailsDialogVisible" modal header="Candidate Profile Details" :style="{ width: '64rem' }">
      <div v-if="detailsLoading" class="flex items-center justify-center py-16">
        <ProgressSpinner stroke-width="4" />
      </div>
      <div v-else-if="selectedProfile" class="space-y-6">
        <div>
          <h3 class="text-2xl font-semibold text-slate-900">{{ selectedProfile.full_name }}</h3>
          <p class="mt-1 text-sm text-slate-500">{{ selectedProfile.headline }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Years</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedProfile.years_experience }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3 xl:col-span-3">
            <p class="text-slate-500">Summary</p>
            <p class="mt-1 text-sm leading-6 text-slate-700">{{ selectedProfile.base_summary }}</p>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Core Skills</h4>
            <div class="flex flex-wrap gap-2">
              <Tag v-for="skill in selectedProfile.core_skills" :key="`core-${skill}`" :value="skill" severity="success" />
            </div>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Nice to Have Skills</h4>
            <div class="flex flex-wrap gap-2">
              <Tag v-for="skill in selectedProfile.nice_to_have_skills" :key="`nice-${skill}`" :value="skill" severity="info" />
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">Experiences</h4>
          <div class="space-y-4">
            <div v-for="experience in selectedProfile.experiences" :key="experience.id || `${experience.company}-${experience.title}`" class="rounded-2xl bg-white p-4">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p class="font-medium text-slate-900">{{ experience.title }}</p>
                  <p class="text-sm text-slate-500">{{ experience.company }}</p>
                </div>
                <p class="text-sm text-slate-500">{{ formatDate(experience.start_date) }} - {{ formatDate(experience.end_date) }}</p>
              </div>
              <p class="mt-3 text-sm leading-6 text-slate-700">{{ experience.description }}</p>
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">Projects</h4>
          <div class="space-y-4">
            <div v-for="project in selectedProfile.projects" :key="project.id || project.name" class="rounded-2xl bg-white p-4">
              <p class="font-medium text-slate-900">{{ project.name }}</p>
              <p class="mt-2 text-sm leading-6 text-slate-700">{{ project.description }}</p>
              <div class="mt-3 flex flex-wrap gap-2">
                <Tag v-for="skill in project.skills" :key="`${project.name}-${skill}`" :value="skill" severity="warn" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </Dialog>

    <Dialog v-model:visible="importDialogVisible" modal header="Import Candidate Profile JSON" :style="{ width: '58rem' }">
      <div class="space-y-4">
        <p class="text-sm text-slate-600">Edit the JSON if needed, then import it into the backend using the dedicated import endpoint.</p>
        <Textarea v-model="importJson" fluid auto-resize rows="24" />
        <FormError :message="importError" />
        <div v-if="importBackendError" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ importBackendError }}
        </div>
        <div v-if="importValidationMessages.length > 0" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          <ul class="list-disc space-y-1 pl-5">
            <li v-for="message in importValidationMessages" :key="message">{{ message }}</li>
          </ul>
        </div>
        <div class="flex justify-end gap-3">
          <Button label="Cancel" severity="secondary" text @click="importDialogVisible = false" />
          <LoadingButton :loading="importing" label="Import Profile" loading-label="Importing..." @click="submitImport" />
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import Button from 'primevue/button'
import Chips from 'primevue/chips'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

import type { CandidateExperience, CandidateProfile, CandidateProfilePayload, CandidateProject } from '@/modules/candidate-profile/types'
import {
  createProfile,
  deleteProfile,
  getProfile,
  importProfile,
  listProfiles,
  updateProfile,
} from '@/modules/candidate-profile/services/candidateProfilesApi'
import EmptyState from '@/shared/components/EmptyState.vue'
import ErrorState from '@/shared/components/ErrorState.vue'
import FormError from '@/shared/components/FormError.vue'
import LoadingButton from '@/shared/components/LoadingButton.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
import SkeletonTable from '@/shared/components/SkeletonTable.vue'
import { useDebouncedValue } from '@/shared/composables/useDebouncedValue'
import { getApiErrorMessage, getApiValidationErrors } from '@/shared/utils/api'

interface FormExperience extends CandidateExperience {
  key: number
  start_date: Date | null
  end_date: Date | null
}

interface FormProject extends CandidateProject {
  key: number
}

interface CandidateProfileFormState {
  full_name: string
  headline: string
  base_summary: string
  years_experience: number | null
  preferred_roles: string[]
  preferred_locations: string[]
  preferred_job_types: string[]
  core_skills: string[]
  nice_to_have_skills: string[]
  experiences: FormExperience[]
  projects: FormProject[]
}

const SAMPLE_IMPORT_PROFILE = {
  full_name: 'Hesham Hasanat',
  headline: 'Senior Laravel Backend Engineer',
  base_summary: 'Senior backend developer with strong experience in Laravel, REST APIs, PostgreSQL, Redis, queues, OpenSearch, AWS, Docker, and scalable backend systems.',
  years_experience: 10,
  preferred_roles: ['Senior Backend Engineer', 'Senior Laravel Developer', 'PHP Backend Engineer'],
  preferred_locations: ['Remote', 'UAE', 'Saudi Arabia', 'Europe'],
  preferred_job_types: ['remote', 'hybrid', 'full-time'],
  core_skills: ['PHP', 'Laravel', 'PostgreSQL', 'Redis', 'REST APIs', 'Docker', 'AWS', 'OpenSearch', 'Queues', 'Testing', 'Clean Architecture'],
  nice_to_have_skills: ['Vue.js', 'Kubernetes', 'Terraform', 'Bedrock', 'CI/CD'],
  experiences: [
    {
      company: 'Reach Digital Hub',
      title: 'Senior PHP Developer',
      start_date: '2023-07-01',
      end_date: null,
      description: 'Built scalable Laravel APIs, AI-driven financial scoring, OpenSearch-powered search, queues, and cloud integrations.',
    },
  ],
  projects: [
    {
      name: 'AI Job Platform',
      description: 'Job portal with CV search, OpenSearch indexing, interview scheduling, and recruitment automation.',
      skills: ['Laravel', 'OpenSearch', 'Redis', 'PostgreSQL', 'Queues'],
    },
  ],
}

const toast = useToast()
const confirm = useConfirm()

const loading = ref(false)
const saving = ref(false)
const importing = ref(false)
const detailsLoading = ref(false)
const pageError = ref('')
const formError = ref('')
const importError = ref('')
const importBackendError = ref('')
const query = ref('')
const debouncedQuery = useDebouncedValue(query, 250)
const profiles = ref<CandidateProfile[]>([])
const selectedProfile = ref<CandidateProfile | null>(null)
const editingProfileId = ref<number | null>(null)
const formDialogVisible = ref(false)
const detailsDialogVisible = ref(false)
const importDialogVisible = ref(false)
const validationErrors = ref<Record<string, string[]>>({})
const importValidationErrors = ref<Record<string, string[]>>({})
const importJson = ref(JSON.stringify(SAMPLE_IMPORT_PROFILE, null, 2))
const keySeed = ref(1)

const form = reactive<CandidateProfileFormState>(createDefaultForm())

const filteredProfiles = computed(() => {
  const search = debouncedQuery.value.trim().toLowerCase()

  return profiles.value.filter((profile) => {
    if (!search) {
      return true
    }

    return [profile.full_name, profile.headline].some((value) => value.toLowerCase().includes(search))
  })
})

const formDialogTitle = computed(() => (editingProfileId.value ? 'Edit Candidate Profile' : 'Create Candidate Profile'))
const importValidationMessages = computed(() => Object.values(importValidationErrors.value).flat())

onMounted(async () => {
  await loadProfiles()
})

async function loadProfiles(): Promise<void> {
  loading.value = true
  pageError.value = ''

  try {
    const collection = await listProfiles()
    profiles.value = collection.items
  } catch (error) {
    pageError.value = getApiErrorMessage(error, 'Failed to load candidate profiles.')
  } finally {
    loading.value = false
  }
}

function openCreateDialog(): void {
  editingProfileId.value = null
  resetForm()
  formDialogVisible.value = true
}

async function openEditDialog(id: number): Promise<void> {
  try {
    const profile = await getProfile(id)
    editingProfileId.value = id
    populateForm(profile)
    formDialogVisible.value = true
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Load failed', detail: getApiErrorMessage(error, 'Failed to load profile for editing.'), life: 4000 })
  }
}

async function openDetailsDialog(id: number): Promise<void> {
  detailsDialogVisible.value = true
  detailsLoading.value = true

  try {
    selectedProfile.value = await getProfile(id)
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Load failed', detail: getApiErrorMessage(error, 'Failed to load profile details.'), life: 4000 })
    detailsDialogVisible.value = false
  } finally {
    detailsLoading.value = false
  }
}

function confirmDelete(profile: CandidateProfile): void {
  confirm.require({
    header: 'Delete candidate profile',
    message: `Delete "${profile.full_name}"? This cannot be undone.`,
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await deleteProfile(profile.id)
        toast.add({ severity: 'success', summary: 'Profile deleted', detail: 'Candidate profile removed.', life: 3000 })
        await loadProfiles()
      } catch (error) {
        toast.add({ severity: 'error', summary: 'Delete failed', detail: getApiErrorMessage(error, 'Failed to delete profile.'), life: 4000 })
      }
    },
  })
}

async function duplicateProfile(id: number): Promise<void> {
  try {
    const profile = await getProfile(id)
    editingProfileId.value = null
    populateForm({
      ...profile,
      full_name: `${profile.full_name} Copy`,
    })
    formDialogVisible.value = true
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Duplicate failed', detail: getApiErrorMessage(error, 'Failed to duplicate profile.'), life: 4000 })
  }
}

async function submitForm(): Promise<void> {
  validationErrors.value = {}
  formError.value = ''

  const payload = buildPayload()
  const clientErrors = validatePayload(payload)
  if (Object.keys(clientErrors).length > 0) {
    validationErrors.value = Object.fromEntries(Object.entries(clientErrors).map(([key, value]) => [key, [value]]))
    return
  }

  saving.value = true

  try {
    if (editingProfileId.value) {
      const profile = await updateProfile(editingProfileId.value, payload)
      upsertProfile(profile)
      toast.add({ severity: 'success', summary: 'Profile updated', detail: 'Candidate profile saved successfully.', life: 3000 })
    } else {
      const profile = await createProfile(payload)
      upsertProfile(profile)
      toast.add({ severity: 'success', summary: 'Profile created', detail: 'Candidate profile created successfully.', life: 3000 })
    }

    formDialogVisible.value = false
  } catch (error) {
    validationErrors.value = getApiValidationErrors(error)
    formError.value = getApiErrorMessage(error, 'Failed to save candidate profile.')
  } finally {
    saving.value = false
  }
}

function openImportDialog(): void {
  importJson.value = JSON.stringify(SAMPLE_IMPORT_PROFILE, null, 2)
  importError.value = ''
  importBackendError.value = ''
  importValidationErrors.value = {}
  importDialogVisible.value = true
}

async function submitImport(): Promise<void> {
  importError.value = ''
  importBackendError.value = ''
  importValidationErrors.value = {}

  let parsed: CandidateProfilePayload
  try {
    parsed = JSON.parse(importJson.value) as CandidateProfilePayload
  } catch {
    importError.value = 'Import JSON is not valid.'
    return
  }

  const clientErrors = validatePayload(parsed)
  if (Object.keys(clientErrors).length > 0) {
    importError.value = Object.values(clientErrors)[0]
    return
  }

  importing.value = true

  try {
    const profile = await importProfile(parsed)
    upsertProfile(profile)
    importDialogVisible.value = false
    toast.add({ severity: 'success', summary: 'Profile imported', detail: 'Candidate profile imported successfully.', life: 3000 })
  } catch (error) {
    importValidationErrors.value = getApiValidationErrors(error)
    importBackendError.value = getApiErrorMessage(error, 'Failed to import profile.')
  } finally {
    importing.value = false
  }
}

function addExperience(): void {
  form.experiences.push(createExperience())
}

function removeExperience(index: number): void {
  form.experiences.splice(index, 1)
}

function addProject(): void {
  form.projects.push(createProject())
}

function removeProject(index: number): void {
  form.projects.splice(index, 1)
}

function buildPayload(): CandidateProfilePayload {
  return {
    full_name: form.full_name.trim(),
    headline: form.headline.trim(),
    base_summary: form.base_summary.trim(),
    years_experience: typeof form.years_experience === 'number' ? form.years_experience : Number.NaN,
    preferred_roles: sanitizeStringArray(form.preferred_roles),
    preferred_locations: sanitizeStringArray(form.preferred_locations),
    preferred_job_types: sanitizeStringArray(form.preferred_job_types),
    core_skills: sanitizeStringArray(form.core_skills),
    nice_to_have_skills: sanitizeStringArray(form.nice_to_have_skills),
    experiences: form.experiences.map((experience) => ({
      company: experience.company.trim(),
      title: experience.title.trim(),
      start_date: formatDateForApi(experience.start_date),
      end_date: formatDateForApi(experience.end_date),
      description: experience.description.trim(),
    })),
    projects: form.projects.map((project) => ({
      name: project.name.trim(),
      description: project.description.trim(),
      skills: sanitizeStringArray(project.skills),
    })),
  }
}

function validatePayload(payload: CandidateProfilePayload): Record<string, string> {
  const errors: Record<string, string> = {}

  if (!payload.full_name) {
    errors.full_name = 'Full name is required.'
  }
  if (!payload.headline) {
    errors.headline = 'Headline is required.'
  }
  if (!payload.base_summary) {
    errors.base_summary = 'Base summary is required.'
  }
  if (!Number.isFinite(payload.years_experience)) {
    errors.years_experience = 'Years of experience is required.'
  }

  payload.experiences.forEach((experience, index) => {
    if (!experience.company) {
      errors[`experiences.${index}.company`] = 'Company is required.'
    }
    if (!experience.title) {
      errors[`experiences.${index}.title`] = 'Title is required.'
    }
    if (!experience.description) {
      errors[`experiences.${index}.description`] = 'Description is required.'
    }
  })

  payload.projects.forEach((project, index) => {
    if (!project.name) {
      errors[`projects.${index}.name`] = 'Project name is required.'
    }
    if (!project.description) {
      errors[`projects.${index}.description`] = 'Project description is required.'
    }
  })

  return errors
}

function populateForm(profile: CandidateProfile): void {
  validationErrors.value = {}
  formError.value = ''
  form.full_name = profile.full_name
  form.headline = profile.headline
  form.base_summary = profile.base_summary
  form.years_experience = profile.years_experience
  form.preferred_roles = [...profile.preferred_roles]
  form.preferred_locations = [...profile.preferred_locations]
  form.preferred_job_types = [...profile.preferred_job_types]
  form.core_skills = [...profile.core_skills]
  form.nice_to_have_skills = [...profile.nice_to_have_skills]
  form.experiences = (profile.experiences || []).map((experience) => ({
    key: nextKey(),
    id: experience.id,
    company: experience.company,
    title: experience.title,
    start_date: parseDate(experience.start_date),
    end_date: parseDate(experience.end_date),
    description: experience.description,
  }))
  form.projects = (profile.projects || []).map((project) => ({
    key: nextKey(),
    id: project.id,
    name: project.name,
    description: project.description,
    skills: [...project.skills],
  }))
}

function resetForm(): void {
  Object.assign(form, createDefaultForm())
  validationErrors.value = {}
  formError.value = ''
}

function createDefaultForm(): CandidateProfileFormState {
  return {
    full_name: '',
    headline: '',
    base_summary: '',
    years_experience: null,
    preferred_roles: [],
    preferred_locations: [],
    preferred_job_types: [],
    core_skills: [],
    nice_to_have_skills: [],
    experiences: [createExperience()],
    projects: [createProject()],
  }
}

function createExperience(): FormExperience {
  return {
    key: nextKey(),
    company: '',
    title: '',
    start_date: null,
    end_date: null,
    description: '',
  }
}

function createProject(): FormProject {
  return {
    key: nextKey(),
    name: '',
    description: '',
    skills: [],
  }
}

function nextKey(): number {
  return keySeed.value++
}

function sanitizeStringArray(values: string[]): string[] {
  return values.map((value) => value.trim()).filter(Boolean)
}

function formatDateForApi(value: Date | null): string | null {
  if (!value) {
    return null
  }

  return value.toISOString().slice(0, 10)
}

function parseDate(value?: string | null): Date | null {
  return value ? new Date(value) : null
}

function upsertProfile(profile: CandidateProfile): void {
  const index = profiles.value.findIndex((item) => item.id === profile.id)
  if (index === -1) {
    profiles.value.unshift(profile)
    return
  }

  profiles.value.splice(index, 1, profile)
}

function fieldError(field: string): string | null {
  return validationErrors.value[field]?.[0] ?? null
}

function nestedFieldError(field: string): string | null {
  return validationErrors.value[field]?.[0] ?? null
}

function formatDate(value?: string | null): string {
  if (!value) {
    return 'Present'
  }

  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
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
