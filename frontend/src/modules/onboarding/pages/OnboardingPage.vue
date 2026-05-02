<template>
  <div class="mx-auto max-w-6xl space-y-6">
    <PageHeader
      eyebrow="Guided setup"
      title="Build your Job Seeker Copilot"
      description="Start with your career profile, review what the system understood, then choose the job paths you want to pursue."
    />

    <div class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-3 shadow-sm md:grid-cols-5">
      <button
        v-for="(step, index) in steps"
        :key="step"
        type="button"
        class="rounded-2xl px-4 py-3 text-left text-sm transition"
        :class="activeStep === index ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-50 text-slate-600'"
        @click="activeStep = Math.min(index, maxUnlockedStep)"
      >
        <span class="block text-xs font-semibold uppercase tracking-[0.18em] opacity-75">Step {{ index + 1 }}</span>
        <span class="mt-1 block font-semibold">{{ step }}</span>
      </button>
    </div>

    <ErrorState v-if="pageError" title="Could not continue onboarding" :message="pageError" @retry="loadOnboarding" />

    <section v-else class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
      <div v-if="activeStep === 0" class="space-y-6">
        <div>
          <h2 class="text-2xl font-semibold text-slate-900">Tell us who you are professionally</h2>
          <p class="mt-2 text-sm text-slate-600">Manual input is supported now. CV file parsing can be added later; for now you can paste CV text as reference.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Display name</label>
            <InputText v-model="profileForm.display_name" fluid />
            <FormError :message="fieldError('display_name')" />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Headline</label>
            <InputText v-model="profileForm.title" fluid placeholder="Senior Laravel Backend Engineer" />
            <FormError :message="fieldError('title')" />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Primary role</label>
            <InputText v-model="profileForm.primary_role" fluid placeholder="Backend Developer" />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Seniority</label>
            <Select v-model="profileForm.seniority_level" :options="seniorityOptions" fluid placeholder="Select seniority" />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Years of experience</label>
            <InputNumber v-model="profileForm.years_of_experience" fluid :min="0" :max="60" />
            <FormError :message="fieldError('years_of_experience')" />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Work preference</label>
            <Select v-model="profileForm.preferred_workplace_type" :options="workplaceOptions" fluid />
          </div>
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Professional summary</label>
          <Textarea v-model="profileForm.professional_summary" fluid auto-resize rows="5" />
          <FormError :message="fieldError('professional_summary')" />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Core skills</label>
            <Chips v-model="profileForm.skills" fluid separator="," />
            <FormError :message="fieldError('skills')" />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Secondary skills</label>
            <Chips v-model="profileForm.secondary_skills" fluid separator="," />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Industries</label>
            <Chips v-model="profileForm.industries" fluid separator="," />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Preferred locations</label>
            <Chips v-model="profileForm.preferred_locations" fluid separator="," />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Preferred job types</label>
            <Chips v-model="profileForm.preferred_job_types" fluid separator="," />
          </div>
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Paste CV text, optional</label>
          <Textarea v-model="profileForm.raw_cv_text" fluid auto-resize rows="6" placeholder="Paste CV text here if you want to keep it for future parsing." />
        </div>

        <div class="flex justify-end">
          <LoadingButton label="Review profile" icon="pi pi-arrow-right" :loading="savingProfile" @click="saveProfile" />
        </div>
      </div>

      <div v-else-if="activeStep === 1" class="space-y-6">
        <div>
          <h2 class="text-2xl font-semibold text-slate-900">We understood you as...</h2>
          <p class="mt-2 text-sm text-slate-600">Review this summary. If it looks wrong, go back and edit your profile before continuing.</p>
        </div>

        <div v-if="understanding" class="grid gap-4 md:grid-cols-2">
          <div class="rounded-2xl bg-slate-50 p-5">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Role</p>
            <p class="mt-2 text-xl font-semibold text-slate-900">{{ understanding.role }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-5">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Seniority</p>
            <p class="mt-2 text-xl font-semibold text-slate-900">{{ understanding.seniority }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-5 md:col-span-2">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Skills</p>
            <div class="mt-3 flex flex-wrap gap-2">
              <Tag v-for="skill in understanding.skills" :key="skill" :value="skill" severity="info" />
            </div>
          </div>
          <div class="rounded-2xl bg-slate-50 p-5 md:col-span-2">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Suggested directions</p>
            <div class="mt-3 flex flex-wrap gap-2">
              <Tag v-for="direction in understanding.suggested_job_directions" :key="direction" :value="direction" severity="success" />
            </div>
          </div>
        </div>

        <div class="flex flex-wrap justify-between gap-3">
          <Button label="Edit profile" icon="pi pi-arrow-left" severity="secondary" @click="activeStep = 0" />
          <LoadingButton label="Suggest job paths" icon="pi pi-sparkles" :loading="loadingSuggestions" @click="loadSuggestions" />
        </div>
      </div>

      <div v-else-if="activeStep === 2" class="space-y-6">
        <div>
          <h2 class="text-2xl font-semibold text-slate-900">Choose your Job Paths</h2>
          <p class="mt-2 text-sm text-slate-600">Select the directions you want the system to use later for finding and ranking jobs.</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <article
            v-for="(suggestion, index) in suggestions"
            :key="`${suggestion.name}-${index}`"
            class="rounded-3xl border p-5 transition"
            :class="selectedSuggestionIndexes.includes(index) ? 'border-sky-300 bg-sky-50/60' : 'border-slate-200 bg-white'"
          >
            <div class="mb-4 flex items-start justify-between gap-4">
              <Checkbox v-model="selectedSuggestionIndexes" :value="index" />
              <Tag :value="suggestion.remote_preference ?? 'any'" severity="info" />
            </div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Path name</label>
            <InputText v-model="suggestion.name" fluid />
            <label class="mb-2 mt-4 block text-sm font-medium text-slate-700">Description</label>
            <Textarea v-model="suggestion.description" fluid auto-resize rows="3" />
            <div class="mt-4 flex flex-wrap gap-2">
              <Tag v-for="skill in suggestion.required_skills" :key="skill" :value="skill" severity="success" />
            </div>
          </article>
        </div>

        <div class="flex flex-wrap justify-between gap-3">
          <Button label="Back" icon="pi pi-arrow-left" severity="secondary" @click="activeStep = 1" />
          <Button label="Set preferences" icon="pi pi-arrow-right" @click="activeStep = 3" />
        </div>
      </div>

      <div v-else-if="activeStep === 3" class="space-y-6">
        <div>
          <h2 class="text-2xl font-semibold text-slate-900">Final preferences</h2>
          <p class="mt-2 text-sm text-slate-600">These preferences will be applied to the selected Job Paths.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <label class="flex items-center gap-3 rounded-2xl border border-slate-200 p-5">
            <Checkbox v-model="preferences.auto_collect_enabled" binary />
            <span>
              <span class="block font-semibold text-slate-900">Auto collect later</span>
              <span class="text-sm text-slate-500">Prepared for future automation. No scraping runs in this phase.</span>
            </span>
          </label>
          <label class="flex items-center gap-3 rounded-2xl border border-slate-200 p-5">
            <Checkbox v-model="preferences.notifications_enabled" binary />
            <span>
              <span class="block font-semibold text-slate-900">Notify me about strong matches</span>
              <span class="text-sm text-slate-500">Stored on the Job Path for the next notification phase.</span>
            </span>
          </label>
        </div>

        <div class="flex flex-wrap justify-between gap-3">
          <Button label="Back" icon="pi pi-arrow-left" severity="secondary" @click="activeStep = 2" />
          <LoadingButton label="Finish setup" icon="pi pi-check" :loading="finishing" @click="finishSetup" />
        </div>
      </div>

      <div v-else class="space-y-6 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
          <i class="pi pi-check text-2xl" />
        </div>
        <div>
          <h2 class="text-2xl font-semibold text-slate-900">Your copilot is ready</h2>
          <p class="mt-2 text-sm text-slate-600">You created a Career Profile and selected Job Paths. Next, review your Best Matches.</p>
        </div>
        <Button label="Open Best Matches" icon="pi pi-star" @click="router.push('/matches')" />
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Chips from 'primevue/chips'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'

import { useOnboardingStore } from '@/app/stores/onboardingStore'
import LoadingButton from '@/shared/components/LoadingButton.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
import ErrorState from '@/shared/components/ErrorState.vue'
import FormError from '@/shared/components/FormError.vue'
import { getApiErrorMessage, getApiValidationErrors } from '@/shared/utils/api'
import { createJobPath } from '@/modules/job-paths/services/jobPathsApi'
import { completeOnboarding, saveOnboardingCareerProfile, suggestOnboardingJobPaths } from '@/modules/onboarding/services/onboardingApi'
import type { CareerUnderstanding, OnboardingCareerProfilePayload, SuggestedJobPath } from '@/modules/onboarding/types'

const router = useRouter()
const toast = useToast()
const onboardingStore = useOnboardingStore()

const steps = ['Profile', 'Review', 'Job Paths', 'Preferences', 'Done']
const activeStep = ref(0)
const maxUnlockedStep = ref(0)
const pageError = ref('')
const savingProfile = ref(false)
const loadingSuggestions = ref(false)
const finishing = ref(false)
const validationErrors = ref<Record<string, string[]>>({})
const understanding = ref<CareerUnderstanding | null>(null)
const careerProfileId = ref<number | null>(null)
const suggestions = ref<SuggestedJobPath[]>([])
const selectedSuggestionIndexes = ref<number[]>([])
const preferences = ref({
  auto_collect_enabled: false,
  notifications_enabled: true,
})

const seniorityOptions = ['entry', 'junior', 'mid', 'senior', 'lead', 'principal']
const workplaceOptions = ['remote', 'hybrid', 'onsite', 'any']

const profileForm = ref<OnboardingCareerProfilePayload>({
  display_name: '',
  title: '',
  professional_summary: '',
  primary_role: '',
  seniority_level: 'senior',
  years_of_experience: 5,
  skills: [],
  secondary_skills: [],
  industries: [],
  preferred_workplace_type: 'remote',
  preferred_locations: ['Remote'],
  preferred_job_types: ['full-time'],
  raw_cv_text: '',
  source: 'manual',
})

const selectedSuggestions = computed(() => selectedSuggestionIndexes.value.map((index) => suggestions.value[index]).filter(Boolean))

onMounted(() => {
  void loadOnboarding()
})

async function loadOnboarding(): Promise<void> {
  pageError.value = ''

  const payload = await onboardingStore.fetchOnboarding(true)

  if (!payload) {
    pageError.value = 'The onboarding state could not be loaded. Please retry.'
    return
  }

  understanding.value = payload.understanding
  careerProfileId.value = payload.career_profile?.id ?? null

  if (payload.state.is_completed) {
    activeStep.value = 4
    maxUnlockedStep.value = 4
    return
  }

  if (payload.state.current_step === 'review_profile') {
    activeStep.value = 1
    maxUnlockedStep.value = 1
  } else if (payload.state.current_step === 'suggest_job_paths') {
    activeStep.value = 2
    maxUnlockedStep.value = 2
    const metadataSuggestions = payload.state.metadata.suggested_job_paths
    if (Array.isArray(metadataSuggestions)) {
      suggestions.value = metadataSuggestions as SuggestedJobPath[]
      selectedSuggestionIndexes.value = suggestions.value.map((_, index) => index)
    }
  }
}

async function saveProfile(): Promise<void> {
  savingProfile.value = true
  validationErrors.value = {}

  try {
    const response = await saveOnboardingCareerProfile(profileForm.value)
    onboardingStore.setPayload({
      state: response.state,
      career_profile: response.career_profile,
      understanding: response.understanding,
    })
    understanding.value = response.understanding
    careerProfileId.value = response.career_profile.id
    activeStep.value = 1
    maxUnlockedStep.value = Math.max(maxUnlockedStep.value, 1)
    toast.add({ severity: 'success', summary: 'Career profile saved', detail: 'Review what the system understood.', life: 3000 })
  } catch (error) {
    validationErrors.value = getApiValidationErrors(error)
    toast.add({ severity: 'error', summary: 'Profile not saved', detail: getApiErrorMessage(error), life: 5000 })
  } finally {
    savingProfile.value = false
  }
}

async function loadSuggestions(): Promise<void> {
  loadingSuggestions.value = true

  try {
    const response = await suggestOnboardingJobPaths(careerProfileId.value)
    suggestions.value = response.suggestions
    selectedSuggestionIndexes.value = response.suggestions.map((_, index) => index)
    onboardingStore.setPayload({
      state: response.state,
      career_profile: response.career_profile,
      understanding: understanding.value,
    })
    activeStep.value = 2
    maxUnlockedStep.value = Math.max(maxUnlockedStep.value, 2)
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Could not suggest paths', detail: getApiErrorMessage(error), life: 5000 })
  } finally {
    loadingSuggestions.value = false
  }
}

async function finishSetup(): Promise<void> {
  if (selectedSuggestions.value.length === 0) {
    toast.add({ severity: 'warn', summary: 'Select at least one path', detail: 'Choose one Job Path to continue.', life: 3000 })
    return
  }

  finishing.value = true

  try {
    await Promise.all(selectedSuggestions.value.map((suggestion) => createJobPath({
      ...suggestion,
      auto_collect_enabled: preferences.value.auto_collect_enabled,
      notifications_enabled: preferences.value.notifications_enabled,
    })))

    const response = await completeOnboarding()
    onboardingStore.setPayload({
      state: response.state,
      career_profile: null,
      understanding: understanding.value,
      best_matches_path: response.best_matches_path,
    })

    activeStep.value = 4
    maxUnlockedStep.value = 4
    toast.add({ severity: 'success', summary: 'Onboarding complete', detail: 'Your Job Paths are ready.', life: 3000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Could not finish setup', detail: getApiErrorMessage(error), life: 5000 })
  } finally {
    finishing.value = false
  }
}

function fieldError(field: string): string | undefined {
  return validationErrors.value[field]?.[0]
}
</script>
