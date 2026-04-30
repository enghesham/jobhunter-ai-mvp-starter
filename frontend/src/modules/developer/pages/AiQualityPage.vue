<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <PageHeader
        eyebrow="Developer"
        title="AI Quality"
        description="Track provider reliability, fallback usage, duration, cache behavior, and recent AI quality signals across the workflow."
      />

      <Button label="Refresh" icon="pi pi-refresh" :loading="loading" class="lg:mt-8" @click="loadReport" />
    </div>

    <ErrorState v-if="errorMessage" title="AI quality unavailable" :message="errorMessage">
      <template #actions>
        <Button label="Retry" icon="pi pi-refresh" @click="loadReport" />
      </template>
    </ErrorState>

    <div v-if="loading" class="space-y-6">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div v-for="item in 4" :key="item" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
          <Skeleton width="3rem" height="3rem" class="rounded-2xl" />
          <Skeleton width="8rem" height="1.1rem" class="mt-5" />
          <Skeleton width="5rem" height="2rem" class="mt-3" />
          <Skeleton width="100%" height="0.9rem" class="mt-4" />
        </div>
      </div>
      <SkeletonTable :columns="7" />
    </div>

    <template v-else-if="report">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="card in kpiCards"
          :key="card.label"
          class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-medium text-slate-500">{{ card.label }}</p>
              <p class="mt-2 text-3xl font-semibold text-slate-950">{{ card.value }}</p>
            </div>
            <div :class="['flex h-12 w-12 items-center justify-center rounded-2xl', card.iconClass]">
              <i :class="['pi text-lg', card.icon]" />
            </div>
          </div>
          <p class="mt-4 text-sm leading-6 text-slate-600">{{ card.description }}</p>
        </article>
      </div>

      <EmptyState
        v-if="report.summary.total_runs === 0"
        title="No AI quality data yet"
        description="Run job analysis, matching, resume tailoring, or application material generation to populate provider metrics."
        icon="pi-chart-line"
      >
        <template #actions>
          <RouterLink to="/jobs">
            <Button label="Go to Jobs" icon="pi pi-briefcase" />
          </RouterLink>
        </template>
      </EmptyState>

      <div v-else class="grid gap-6 xl:grid-cols-[1.4fr_0.9fr]">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
          <div class="mb-5 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
              <h3 class="text-lg font-semibold text-slate-950">Provider Comparison</h3>
              <p class="text-sm text-slate-500">Compare OpenRouter, Gemini, Groq, local, and fallback paths from persisted runs.</p>
            </div>
            <Tag :value="`${report.providers.length} providers`" severity="info" />
          </div>

          <DataTable :value="report.providers" data-key="provider" paginator :rows="8" responsive-layout="scroll">
            <Column field="provider_label" header="Provider">
              <template #body="{ data }">
                <div class="space-y-1">
                  <p class="font-semibold text-slate-900">{{ data.provider_label }}</p>
                  <p class="text-xs text-slate-500">{{ data.top_operation ?? 'No runs yet' }}</p>
                </div>
              </template>
            </Column>
            <Column field="total_runs" header="Runs" sortable />
            <Column header="AI Success">
              <template #body="{ data }">
                <div class="min-w-32">
                  <div class="mb-1 flex justify-between text-xs text-slate-500">
                    <span>{{ data.ai_success_runs }} runs</span>
                    <span>{{ formatPercent(data.ai_success_rate) }}</span>
                  </div>
                  <ProgressBar :value="data.ai_success_rate ?? 0" :show-value="false" class="h-2" />
                </div>
              </template>
            </Column>
            <Column header="Fallback">
              <template #body="{ data }">
                <Tag :value="`${data.fallback_runs} / ${formatPercent(data.fallback_rate)}`" :severity="data.fallback_runs > 0 ? 'warn' : 'success'" />
              </template>
            </Column>
            <Column header="Avg Duration">
              <template #body="{ data }">{{ formatDuration(data.average_duration_ms) }}</template>
            </Column>
            <Column header="Confidence">
              <template #body="{ data }">{{ formatPercent(data.average_confidence_score) }}</template>
            </Column>
            <Column header="Errors">
              <template #body="{ data }">
                <Tag :value="String(data.provider_error_count)" :severity="data.provider_error_count > 0 ? 'danger' : 'success'" />
              </template>
            </Column>
          </DataTable>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
          <h3 class="text-lg font-semibold text-slate-950">Cache Signal</h3>
          <p class="mt-1 text-sm text-slate-500">{{ report.cache.note }}</p>

          <div class="mt-5 rounded-2xl bg-slate-50 p-4">
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="text-sm text-slate-500">Observed cache hit rate</p>
                <p class="mt-1 text-3xl font-semibold text-slate-950">{{ formatPercent(report.cache.hit_rate) }}</p>
              </div>
              <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                <i class="pi pi-history text-xl" />
              </div>
            </div>
            <ProgressBar :value="report.cache.hit_rate ?? 0" class="mt-4 h-2" :show-value="false" />
          </div>

          <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-2xl border border-slate-100 p-4">
              <dt class="text-slate-500">Hits</dt>
              <dd class="mt-1 text-xl font-semibold text-slate-900">{{ report.cache.observed_hits }}</dd>
            </div>
            <div class="rounded-2xl border border-slate-100 p-4">
              <dt class="text-slate-500">Misses</dt>
              <dd class="mt-1 text-xl font-semibold text-slate-900">{{ report.cache.observed_misses }}</dd>
            </div>
            <div class="rounded-2xl border border-slate-100 p-4">
              <dt class="text-slate-500">Cacheable records</dt>
              <dd class="mt-1 text-xl font-semibold text-slate-900">{{ report.cache.cacheable_records }}</dd>
            </div>
            <div class="rounded-2xl border border-slate-100 p-4">
              <dt class="text-slate-500">Duplicate hashes</dt>
              <dd class="mt-1 text-xl font-semibold text-slate-900">{{ report.cache.duplicate_input_hash_records }}</dd>
            </div>
          </dl>
        </section>
      </div>

      <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
        <div class="mb-5">
          <h3 class="text-lg font-semibold text-slate-950">Operation Breakdown</h3>
          <p class="text-sm text-slate-500">See where AI is adding value and where deterministic fallback is carrying the flow.</p>
        </div>

        <DataTable :value="report.operations" data-key="operation" responsive-layout="scroll">
          <Column field="operation_label" header="Operation" />
          <Column field="total_runs" header="Runs" sortable />
          <Column header="Success Rate">
            <template #body="{ data }">
              <Tag :value="formatPercent(data.ai_success_rate)" :severity="rateSeverity(data.ai_success_rate)" />
            </template>
          </Column>
          <Column header="Fallback Rate">
            <template #body="{ data }">
              <Tag :value="formatPercent(data.fallback_rate)" :severity="data.fallback_runs > 0 ? 'warn' : 'success'" />
            </template>
          </Column>
          <Column header="Avg Duration">
            <template #body="{ data }">{{ formatDuration(data.average_duration_ms) }}</template>
          </Column>
          <Column header="Cache Hit">
            <template #body="{ data }">{{ formatPercent(data.cache_hit_rate) }}</template>
          </Column>
        </DataTable>
      </section>

      <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
          <div class="mb-5 flex items-center justify-between gap-4">
            <div>
              <h3 class="text-lg font-semibold text-slate-950">Provider Errors</h3>
              <p class="text-sm text-slate-500">Top sanitized provider failures from the Laravel log.</p>
            </div>
            <Tag :value="`${report.summary.provider_error_count} errors`" :severity="report.summary.provider_error_count > 0 ? 'danger' : 'success'" />
          </div>

          <EmptyState
            v-if="report.top_errors.length === 0"
            title="No provider errors observed"
            description="The current log window does not contain AI provider failures."
            icon="pi-shield"
          />

          <DataTable v-else :value="report.top_errors" data-key="message" paginator :rows="5" responsive-layout="scroll">
            <Column field="provider_label" header="Provider" />
            <Column field="operation_label" header="Operation" />
            <Column field="count" header="Count" sortable />
            <Column header="Message">
              <template #body="{ data }">
                <p class="max-w-xl text-sm leading-6 text-slate-700">{{ data.message }}</p>
              </template>
            </Column>
          </DataTable>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
          <div class="mb-5">
            <h3 class="text-lg font-semibold text-slate-950">Recent Runs</h3>
            <p class="text-sm text-slate-500">Latest persisted AI metadata records for quick debugging.</p>
          </div>

          <DataTable :value="report.recent_runs" paginator :rows="6" responsive-layout="scroll">
            <Column header="Run">
              <template #body="{ data }">
                <div class="space-y-1">
                  <p class="font-semibold text-slate-900">{{ data.operation_label }}</p>
                  <p class="max-w-72 truncate text-xs text-slate-500">{{ data.label }}</p>
                </div>
              </template>
            </Column>
            <Column header="Provider">
              <template #body="{ data }">
                <Tag :value="data.provider_label" :severity="data.fallback_used ? 'warn' : 'info'" />
              </template>
            </Column>
            <Column header="Duration">
              <template #body="{ data }">{{ formatDuration(data.ai_duration_ms) }}</template>
            </Column>
            <Column header="Confidence">
              <template #body="{ data }">{{ formatPercent(data.ai_confidence_score) }}</template>
            </Column>
            <Column header="Outcome">
              <template #body="{ data }">
                <Tag :value="data.fallback_used ? 'Fallback' : 'AI'" :severity="data.fallback_used ? 'warn' : 'success'" />
              </template>
            </Column>
            <Column header="Time">
              <template #body="{ data }">{{ formatDate(data.ai_generated_at ?? data.created_at) }}</template>
            </Column>
          </DataTable>
        </section>
      </div>

      <p class="text-xs text-slate-500">
        Report generated at {{ formatDate(report.generated_at) }}. Provider error messages are sanitized before they reach the UI.
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import ProgressBar from 'primevue/progressbar'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'

import EmptyState from '@/shared/components/EmptyState.vue'
import ErrorState from '@/shared/components/ErrorState.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
import SkeletonTable from '@/shared/components/SkeletonTable.vue'
import { getApiErrorMessage } from '@/shared/utils/api'
import { getAiQualityReport } from '@/modules/developer/services/aiQualityApi'
import type { AiQualityReport } from '@/modules/developer/types'

const report = ref<AiQualityReport | null>(null)
const loading = ref(false)
const errorMessage = ref('')

const kpiCards = computed(() => {
  const summary = report.value?.summary

  return [
    {
      label: 'Total runs',
      value: formatNumber(summary?.total_runs ?? 0),
      description: 'Persisted AI metadata records across analysis, matching, resumes, and application materials.',
      icon: 'pi-bolt',
      iconClass: 'bg-sky-100 text-sky-700',
    },
    {
      label: 'AI success rate',
      value: formatPercent(summary?.ai_success_rate ?? null),
      description: `${formatNumber(summary?.ai_success_runs ?? 0)} runs completed through an AI provider without fallback.`,
      icon: 'pi-check-circle',
      iconClass: 'bg-emerald-100 text-emerald-700',
    },
    {
      label: 'Fallback rate',
      value: formatPercent(summary?.fallback_rate ?? null),
      description: `${formatNumber(summary?.fallback_runs ?? 0)} runs used deterministic fallback instead of provider output.`,
      icon: 'pi-sync',
      iconClass: 'bg-amber-100 text-amber-700',
    },
    {
      label: 'Avg duration',
      value: formatDuration(summary?.average_duration_ms ?? null),
      description: `Average confidence is ${formatPercent(summary?.average_confidence_score ?? null)} across recorded runs.`,
      icon: 'pi-stopwatch',
      iconClass: 'bg-indigo-100 text-indigo-700',
    },
  ]
})

onMounted(() => {
  void loadReport()
})

async function loadReport(): Promise<void> {
  loading.value = true
  errorMessage.value = ''

  try {
    report.value = await getAiQualityReport()
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error, 'Could not load AI quality metrics.')
  } finally {
    loading.value = false
  }
}

function formatPercent(value: number | null | undefined): string {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    return 'N/A'
  }

  return `${Math.round(value * 10) / 10}%`
}

function formatDuration(value: number | null | undefined): string {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    return 'N/A'
  }

  if (value >= 1000) {
    return `${Math.round((value / 1000) * 10) / 10}s`
  }

  return `${Math.round(value)}ms`
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat().format(value)
}

function formatDate(value: string | null | undefined): string {
  if (!value) {
    return 'N/A'
  }

  const parsed = new Date(value)

  if (Number.isNaN(parsed.getTime())) {
    return 'N/A'
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(parsed)
}

function rateSeverity(value: number | null | undefined): 'success' | 'warn' | 'danger' | 'secondary' {
  if (typeof value !== 'number') {
    return 'secondary'
  }

  if (value >= 80) {
    return 'success'
  }

  if (value >= 50) {
    return 'warn'
  }

  return 'danger'
}
</script>
