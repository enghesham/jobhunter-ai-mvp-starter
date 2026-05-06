<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Opportunity Controls"
      title="Tune your opportunity thresholds"
      description="Control what gets collected, what appears in Opportunities, and what becomes a Best Match after evaluation."
    />

    <ErrorState v-if="settingsError" title="Opportunity controls unavailable" :message="settingsError">
      <template #actions>
        <Button label="Retry" icon="pi pi-refresh" @click="loadOpportunityPreferences" />
      </template>
    </ErrorState>

    <SkeletonTable v-if="settingsLoading" :columns="3" />

    <div v-else-if="opportunityPreferences" class="space-y-5">
      <div class="rounded-3xl border border-sky-100 bg-gradient-to-br from-white via-sky-50/70 to-emerald-50/60 p-5 shadow-sm shadow-slate-200/70">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-sky-700">Filtering Strategy</p>
            <h2 class="mt-2 text-2xl font-semibold text-slate-950">Make the quick filter closer to real evaluation</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
              The quick filter runs before AI. Keeping the quick recommended threshold close to the best match threshold reduces false positives.
              Job Path-specific thresholds still win unless you apply these values to existing paths.
            </p>
          </div>

          <div class="flex flex-col gap-2 sm:flex-row">
            <RouterLink to="/settings">
              <Button label="Settings Home" icon="pi pi-arrow-left" severity="secondary" outlined />
            </RouterLink>
            <LoadingButton
              :loading="settingsSaving"
              label="Save Controls"
              loading-label="Saving..."
              icon="pi pi-save"
              @click="submitOpportunityPreferences"
            />
          </div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
          <div class="rounded-2xl border border-white/80 bg-white/85 p-4 shadow-sm">
            <label class="text-sm font-semibold text-slate-800">Minimum relevance</label>
            <p class="mt-1 min-h-12 text-xs leading-5 text-slate-500">
              {{ opportunityPreferences.descriptions.default_min_relevance_score }}
            </p>
            <InputNumber
              v-model="opportunityForm.default_min_relevance_score"
              class="mt-3"
              fluid
              :min="0"
              :max="100"
              suffix="%"
              :placeholder="String(opportunityPreferences.defaults.default_min_relevance_score)"
            />
            <FormError :message="settingsFieldError('default_min_relevance_score')" />
          </div>

          <div class="rounded-2xl border border-white/80 bg-white/85 p-4 shadow-sm">
            <label class="text-sm font-semibold text-slate-800">Best match threshold</label>
            <p class="mt-1 min-h-12 text-xs leading-5 text-slate-500">
              {{ opportunityPreferences.descriptions.default_min_match_score }}
            </p>
            <InputNumber
              v-model="opportunityForm.default_min_match_score"
              class="mt-3"
              fluid
              :min="0"
              :max="100"
              suffix="%"
              :placeholder="String(opportunityPreferences.defaults.default_min_match_score)"
            />
            <FormError :message="settingsFieldError('default_min_match_score')" />
          </div>

          <div class="rounded-2xl border border-white/80 bg-white/85 p-4 shadow-sm">
            <label class="text-sm font-semibold text-slate-800">Quick recommended threshold</label>
            <p class="mt-1 min-h-12 text-xs leading-5 text-slate-500">
              {{ opportunityPreferences.descriptions.quick_recommended_score }}
            </p>
            <InputNumber
              v-model="opportunityForm.quick_recommended_score"
              class="mt-3"
              fluid
              :min="0"
              :max="100"
              suffix="%"
              :placeholder="String(opportunityPreferences.defaults.quick_recommended_score)"
            />
            <FormError :message="settingsFieldError('quick_recommended_score')" />
          </div>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <button
          v-for="toggle in toggleOptions"
          :key="toggle.key"
          type="button"
          class="group rounded-3xl border p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
          :class="toggleCardClass(toggle.key, toggle.tone)"
          :aria-pressed="String(Boolean(opportunityForm[toggle.key]))"
          @click="opportunityForm[toggle.key] = !opportunityForm[toggle.key]"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="font-semibold" :class="toggleTextClass(toggle.tone)">{{ toggle.title }}</p>
              <p class="mt-1 text-xs leading-5" :class="toggleDescriptionClass(toggle.tone)">{{ toggle.description }}</p>
            </div>
            <span
              class="relative mt-1 inline-flex h-7 w-12 shrink-0 items-center rounded-full border transition"
              :class="opportunityForm[toggle.key] ? 'border-emerald-500 bg-emerald-500' : 'border-slate-300 bg-slate-200'"
            >
              <span
                class="inline-block h-5 w-5 rounded-full bg-white shadow transition"
                :class="opportunityForm[toggle.key] ? 'translate-x-5' : 'translate-x-1'"
              />
            </span>
          </div>
          <p class="mt-3 text-xs font-semibold uppercase tracking-[0.18em]" :class="opportunityForm[toggle.key] ? 'text-emerald-700' : 'text-slate-400'">
            {{ opportunityForm[toggle.key] ? 'Enabled' : 'Disabled' }}
          </p>
        </button>
      </div>

      <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-900">
        Effective values now:
        relevance {{ opportunityPreferences.effective.default_min_relevance_score }}%,
        best match {{ opportunityPreferences.effective.default_min_match_score }}%,
        quick recommended {{ opportunityPreferences.effective.quick_recommended_score }}%.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import { useToast } from 'primevue/usetoast'

import ErrorState from '@/shared/components/ErrorState.vue'
import FormError from '@/shared/components/FormError.vue'
import LoadingButton from '@/shared/components/LoadingButton.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
import SkeletonTable from '@/shared/components/SkeletonTable.vue'
import { getApiErrorMessage, getApiValidationErrors } from '@/shared/utils/api'
import { getOpportunityPreferences, updateOpportunityPreferences } from '@/modules/settings/services/opportunityPreferencesApi'
import type { OpportunityPreferences } from '@/modules/settings/types'

type ToggleKey = 'store_below_threshold' | 'show_below_threshold' | 'apply_to_existing_job_paths'

interface OpportunitySettingsFormState {
  default_min_relevance_score: number | null
  default_min_match_score: number | null
  quick_recommended_score: number | null
  store_below_threshold: boolean
  show_below_threshold: boolean
  apply_to_existing_job_paths: boolean
}

const toast = useToast()
const settingsLoading = ref(false)
const settingsSaving = ref(false)
const settingsError = ref('')
const opportunityPreferences = ref<OpportunityPreferences | null>(null)
const settingsValidationErrors = ref<Record<string, string[]>>({})

const opportunityForm = reactive<OpportunitySettingsFormState>({
  default_min_relevance_score: null,
  default_min_match_score: null,
  quick_recommended_score: null,
  store_below_threshold: false,
  show_below_threshold: false,
  apply_to_existing_job_paths: false,
})

const toggleOptions: Array<{ key: ToggleKey; title: string; description: string; tone: 'slate' | 'sky' | 'amber' }> = [
  {
    key: 'store_below_threshold',
    title: 'Store weak jobs',
    description: 'Keep jobs below the relevance threshold for later review instead of dropping them.',
    tone: 'slate',
  },
  {
    key: 'show_below_threshold',
    title: 'Show weak jobs by default',
    description: 'Show low relevance jobs in Opportunities without requiring Full Review mode.',
    tone: 'sky',
  },
  {
    key: 'apply_to_existing_job_paths',
    title: 'Apply to existing Job Paths',
    description: 'Update current Job Paths to use these relevance and match thresholds.',
    tone: 'amber',
  },
]

onMounted(async () => {
  await loadOpportunityPreferences()
})

async function loadOpportunityPreferences(): Promise<void> {
  settingsLoading.value = true
  settingsError.value = ''

  try {
    const preferences = await getOpportunityPreferences()
    opportunityPreferences.value = preferences
    applyOpportunityPreferences(preferences)
  } catch (error) {
    settingsError.value = getApiErrorMessage(error, 'Failed to load opportunity controls.')
  } finally {
    settingsLoading.value = false
  }
}

async function submitOpportunityPreferences(): Promise<void> {
  settingsSaving.value = true
  settingsValidationErrors.value = {}

  try {
    const applyToExistingPaths = opportunityForm.apply_to_existing_job_paths
    const preferences = await updateOpportunityPreferences({
      default_min_relevance_score: opportunityForm.default_min_relevance_score,
      default_min_match_score: opportunityForm.default_min_match_score,
      quick_recommended_score: opportunityForm.quick_recommended_score,
      store_below_threshold: opportunityForm.store_below_threshold,
      show_below_threshold: opportunityForm.show_below_threshold,
      apply_to_existing_job_paths: applyToExistingPaths,
    })

    opportunityPreferences.value = preferences
    applyOpportunityPreferences(preferences)
    toast.add({
      severity: 'success',
      summary: 'Opportunity controls saved',
      detail: applyToExistingPaths
        ? 'Settings saved and existing Job Paths were updated.'
        : 'Settings saved. Existing Job Paths keep their current thresholds.',
      life: 4000,
    })
  } catch (error) {
    settingsValidationErrors.value = getApiValidationErrors(error)
    toast.add({
      severity: 'error',
      summary: 'Could not save controls',
      detail: getApiErrorMessage(error, 'Failed to save opportunity controls.'),
      life: 5000,
    })
  } finally {
    settingsSaving.value = false
  }
}

function applyOpportunityPreferences(preferences: OpportunityPreferences): void {
  opportunityForm.default_min_relevance_score = preferences.values.default_min_relevance_score ?? preferences.effective.default_min_relevance_score
  opportunityForm.default_min_match_score = preferences.values.default_min_match_score ?? preferences.effective.default_min_match_score
  opportunityForm.quick_recommended_score = preferences.values.quick_recommended_score ?? preferences.effective.quick_recommended_score
  opportunityForm.store_below_threshold = preferences.values.store_below_threshold ?? preferences.effective.store_below_threshold
  opportunityForm.show_below_threshold = preferences.values.show_below_threshold ?? preferences.effective.show_below_threshold
  opportunityForm.apply_to_existing_job_paths = false
}

function settingsFieldError(field: string): string | null {
  return settingsValidationErrors.value[field]?.[0] ?? null
}

function toggleCardClass(key: ToggleKey, tone: string): string {
  if (opportunityForm[key]) {
    if (tone === 'amber') {
      return 'border-amber-300 bg-amber-50'
    }

    if (tone === 'sky') {
      return 'border-sky-300 bg-sky-50'
    }

    return 'border-emerald-300 bg-emerald-50'
  }

  return 'border-slate-200 bg-white'
}

function toggleTextClass(tone: string): string {
  if (tone === 'amber') {
    return 'text-amber-950'
  }

  if (tone === 'sky') {
    return 'text-sky-950'
  }

  return 'text-slate-900'
}

function toggleDescriptionClass(tone: string): string {
  if (tone === 'amber') {
    return 'text-amber-800'
  }

  if (tone === 'sky') {
    return 'text-sky-700'
  }

  return 'text-slate-500'
}
</script>
