<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Copilot"
      title="Opportunities"
      description="Review jobs pre-screened by your Job Paths. Run AI evaluation only on the opportunities you care about."
    >
      <template #actions>
        <Button label="Collect Jobs" icon="pi pi-cloud-download" severity="secondary" :loading="collecting" @click="collectJobs" />
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
      <div class="rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4">
        <div class="grid gap-3 xl:grid-cols-[minmax(18rem,1fr)_14rem_17rem_auto] xl:items-center">
          <IconField class="w-full">
            <InputIcon class="pi pi-search" />
            <InputText v-model.trim="query" fluid placeholder="Search title, company, path, or keyword" />
          </IconField>

          <Select
            v-model="viewFilter"
            :options="viewFilterOptions"
            option-label="label"
            option-value="value"
            fluid
            aria-label="Filter opportunities view"
          />

          <Select
            v-model="scopeFilter"
            :options="scopeFilterOptions"
            option-label="label"
            option-value="value"
            fluid
            aria-label="Filter list scope"
            @change="loadOpportunities"
          />

          <Button
            label="Reset"
            icon="pi pi-filter-slash"
            severity="secondary"
            outlined
            :disabled="!hasActiveFilters"
            @click="resetFilters"
          />
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
          <span class="rounded-full bg-white px-3 py-1 shadow-sm">
            Showing {{ filteredOpportunities.length }} of {{ opportunities.length }}
          </span>
          <Tag :value="activeViewLabel" severity="info" />
          <Tag v-if="includeHidden" value="Hidden included" severity="warn" />
          <Tag v-if="showDuplicates" value="Duplicates visible" severity="secondary" />
          <span v-if="debouncedQuery" class="rounded-full bg-white px-3 py-1 shadow-sm">
            Search: "{{ debouncedQuery }}"
          </span>
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
            <Button label="Collect Jobs" icon="pi pi-cloud-download" severity="secondary" :loading="collecting" @click="collectJobs" />
            <RouterLink to="/job-sources">
              <Button label="Manage Sources" icon="pi pi-database" severity="secondary" outlined />
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

        <Column header="Evaluation">
          <template #body="{ data }">
            <ScoreBadge
              v-if="isEvaluated(data)"
              :score="evaluatedMatchScore(data)"
              label="Match"
            />
            <div v-else class="space-y-1">
              <Tag value="Not evaluated" severity="secondary" icon="pi pi-clock" />
              <p class="text-xs text-slate-500">Pre-screened by Job Path</p>
            </div>
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
                v-if="!isEvaluated(data)"
                label="Evaluate Fit"
                icon="pi pi-sparkles"
                size="small"
                :loading="evaluatingId === data.id"
                :disabled="data.status === 'hidden'"
                @click="evaluate(data)"
              />
              <Tag
                v-else
                :value="evaluationStatusLabel(data)"
                :severity="evaluationStatusSeverity(data)"
                :icon="evaluationStatusIcon(data)"
              />
              <Button
                v-if="canCreateApplyPackage(data)"
                :label="applyPackageActionLabel(data)"
                :icon="applyPackageActionIcon(data)"
                size="small"
                :severity="applyPackageActionSeverity(data)"
                :loading="generatingPackageOpportunityId === data.id"
                @click="openPackageOptions(data)"
              />
              <Button
                v-if="hasApplyPackage(data)"
                label="View Package"
                icon="pi pi-folder-open"
                size="small"
                severity="success"
                outlined
                :loading="loadingPackageOpportunityId === data.id"
                @click="viewPackage(data)"
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
            <p class="text-sm text-slate-500">Pre-screen</p>
            <p class="mt-1 font-medium text-slate-900">
              {{ isEvaluated(selectedOpportunity) ? `${selectedOpportunity.quick_relevance_score}% initial fit` : 'Passed initial filter' }}
            </p>
            <p v-if="!isEvaluated(selectedOpportunity)" class="mt-1 text-xs text-slate-500">No match score until you run Evaluate Fit.</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Match Score</p>
            <p class="mt-1 font-medium text-slate-900">
              {{ isEvaluated(selectedOpportunity) ? `${evaluatedMatchScore(selectedOpportunity)}% / threshold ${matchThreshold(selectedOpportunity)}%` : 'Not evaluated yet' }}
            </p>
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
            <div
              v-if="isLowFitOpportunity(selectedOpportunity)"
              class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900"
            >
              This job is below your apply threshold. You can continue anyway, but review the gaps before creating an application.
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
              <Button
                v-if="!isEvaluated(selectedOpportunity)"
                label="Evaluate Fit"
                icon="pi pi-sparkles"
                size="small"
                :loading="evaluatingId === selectedOpportunity.id"
                @click="evaluate(selectedOpportunity)"
              />
              <Tag
                v-else
                :value="evaluationStatusLabel(selectedOpportunity)"
                :severity="evaluationStatusSeverity(selectedOpportunity)"
                :icon="evaluationStatusIcon(selectedOpportunity)"
              />
              <Button
                v-if="canCreateApplyPackage(selectedOpportunity)"
                :label="applyPackageActionLabel(selectedOpportunity)"
                :icon="applyPackageActionIcon(selectedOpportunity)"
                size="small"
                :severity="applyPackageActionSeverity(selectedOpportunity)"
                :loading="generatingPackageOpportunityId === selectedOpportunity.id"
                @click="openPackageOptions(selectedOpportunity)"
              />
              <Button
                v-if="hasApplyPackage(selectedOpportunity)"
                label="View Apply Package"
                icon="pi pi-folder-open"
                size="small"
                severity="success"
                outlined
                :loading="loadingPackageOpportunityId === selectedOpportunity.id"
                @click="viewPackage(selectedOpportunity)"
              />
              <RouterLink v-if="isFitOpportunity(selectedOpportunity)" to="/matches">
                <Button label="Open Best Matches" icon="pi pi-star" size="small" text />
              </RouterLink>
              <RouterLink v-else-if="isEvaluated(selectedOpportunity)" to="/matches">
                <Button label="Open Match History" icon="pi pi-history" size="small" severity="secondary" text />
              </RouterLink>
            </div>
          </div>
        </div>

        <div v-if="selectedOpportunity.match" class="rounded-3xl border border-sky-200 bg-sky-50 p-4">
          <h4 class="font-semibold text-slate-900">Evaluation result</h4>
          <p class="mt-2 text-sm leading-6 text-slate-700">{{ selectedOpportunity.match.ai_recommendation_summary || selectedOpportunity.match.notes }}</p>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-4">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h4 class="font-semibold text-slate-900">Job Description</h4>
              <p class="mt-1 text-xs text-slate-500">Preserved from the collected source where possible: headings, bullets, and numbered lists.</p>
            </div>
            <Button
              v-if="selectedOpportunity.job?.url"
              label="Open Job"
              icon="pi pi-external-link"
              size="small"
              severity="secondary"
              outlined
              @click="openExternalUrl(selectedOpportunity.job.url)"
            />
          </div>

          <div class="mt-4 max-h-[28rem] overflow-y-auto rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
            <div v-if="selectedDescriptionBlocks.length" class="space-y-4">
              <template v-for="(block, index) in selectedDescriptionBlocks" :key="`${block.type}-${index}`">
                <h5
                  v-if="block.type === 'heading'"
                  class="border-b border-slate-200 pb-2 text-sm font-semibold uppercase tracking-[0.16em] text-slate-700"
                >
                  {{ block.text }}
                </h5>
                <ol v-else-if="block.type === 'list' && block.ordered" class="list-decimal space-y-2 pl-5 text-sm leading-6 text-slate-700">
                  <li v-for="item in block.items" :key="item">{{ item }}</li>
                </ol>
                <ul v-else-if="block.type === 'list'" class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
                  <li v-for="item in block.items" :key="item">{{ item }}</li>
                </ul>
                <p v-else class="text-sm leading-7 text-slate-700">{{ block.text }}</p>
              </template>
            </div>
            <p v-else class="text-sm text-slate-500">No description available.</p>
          </div>
        </div>
      </div>
    </Dialog>

    <Dialog v-model:visible="packageOptionsVisible" modal header="Create Apply Package" :style="{ width: '42rem' }">
      <div class="space-y-5">
        <div>
          <h3 class="text-xl font-semibold text-slate-900">{{ packageOptionsOpportunity?.job?.title || 'Selected opportunity' }}</h3>
          <p class="mt-1 text-sm text-slate-500">Choose only what you need now. Fewer sections means less AI context and faster generation.</p>
        </div>

        <div
          v-if="packageOptionsOpportunity && isLowFitOpportunity(packageOptionsOpportunity)"
          class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900"
        >
          Low-fit override: this job did not pass your threshold. Generate this package only if you intentionally want to continue.
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <label
            v-for="option in packageSectionOptions"
            :key="option.value"
            class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-emerald-300 hover:bg-emerald-50"
          >
            <Checkbox v-model="selectedPackageSections" :value="option.value" />
            <span>
              <span class="block font-medium text-slate-900">{{ option.label }}</span>
              <span class="mt-1 block text-xs leading-5 text-slate-500">{{ option.description }}</span>
            </span>
          </label>
        </div>

        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-6 text-sky-900">
          The package will reuse your answer templates from Settings where available, then adapt content to this job and profile.
        </div>

        <div class="flex justify-end gap-3">
          <Button label="Cancel" severity="secondary" text @click="packageOptionsVisible = false" />
          <Button
            label="Generate Selected"
            icon="pi pi-sparkles"
            severity="success"
            :disabled="selectedPackageSections.length === 0 || !packageOptionsOpportunity"
            :loading="Boolean(packageOptionsOpportunity && generatingPackageOpportunityId === packageOptionsOpportunity.id)"
            @click="confirmCreatePackage"
          />
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

        <div
          v-if="packageContinuedAnyway(selectedApplyPackage)"
          class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900"
        >
          This package was created after you chose to continue with a job below your match threshold. Use it carefully and review the gaps before applying.
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
import Select from 'primevue/select'
import Tag from 'primevue/tag'

import {
  collectJobsForActivePaths,
  evaluateOpportunity,
  hideOpportunity,
  listOpportunities,
  refreshOpportunities,
  restoreOpportunity,
} from '@/modules/opportunities/services/opportunitiesApi'
import {
  createApplicationFromApplyPackage,
  generateApplyPackage,
  getApplyPackage,
} from '@/modules/apply-packages/services/applyPackagesApi'
import type { ApplyPackage, ApplyPackageAnswer, ApplyPackageSection } from '@/modules/apply-packages/types'
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

type DescriptionBlock =
  | { type: 'heading'; text: string }
  | { type: 'paragraph'; text: string }
  | { type: 'list'; ordered: boolean; items: string[] }
type OpportunityViewFilter = 'all' | 'best' | 'evaluated' | 'pending'
type OpportunityScopeFilter = 'default' | 'include_hidden' | 'show_duplicates' | 'expanded'

const toast = useToast()
const router = useRouter()
const loading = ref(false)
const refreshing = ref(false)
const collecting = ref(false)
const evaluatingId = ref<number | null>(null)
const generatingPackageOpportunityId = ref<number | null>(null)
const loadingPackageOpportunityId = ref<number | null>(null)
const creatingApplication = ref(false)
const downloadingResumeId = ref<number | null>(null)
const errorMessage = ref('')
const query = ref('')
const debouncedQuery = useDebouncedValue(query, 250)
const viewFilter = ref<OpportunityViewFilter>('all')
const scopeFilter = ref<OpportunityScopeFilter>('default')
const opportunities = ref<JobOpportunity[]>([])
const selectedOpportunity = ref<JobOpportunity | null>(null)
const selectedApplyPackage = ref<ApplyPackage | null>(null)
const detailsVisible = ref(false)
const applyPackageVisible = ref(false)
const packageOptionsVisible = ref(false)
const packageOptionsOpportunity = ref<JobOpportunity | null>(null)
const selectedPackageSections = ref<ApplyPackageSection[]>([
  'tailored_resume',
  'cover_letter',
  'application_answers',
  'salary_answer',
  'notice_period_answer',
  'interest_answer',
  'strengths_gaps',
  'interview_questions',
  'follow_up_email',
])

const packageSectionOptions: Array<{ value: ApplyPackageSection; label: string; description: string }> = [
  { value: 'tailored_resume', label: 'Tailored resume', description: 'Generate or reuse the tailored CV/PDF for this role.' },
  { value: 'cover_letter', label: 'Cover letter', description: 'A job-specific letter based on your facts.' },
  { value: 'application_answers', label: 'Short answers', description: 'Reusable answers from your templates.' },
  { value: 'salary_answer', label: 'Salary answer', description: 'Compensation response for forms.' },
  { value: 'notice_period_answer', label: 'Notice period', description: 'A clean answer for availability timing.' },
  { value: 'interest_answer', label: 'Why interested', description: 'Why this role/company fits you.' },
  { value: 'strengths_gaps', label: 'Strengths and gaps', description: 'What to highlight and what to be careful about.' },
  { value: 'interview_questions', label: 'Interview prep', description: 'Questions to ask or prepare for.' },
  { value: 'follow_up_email', label: 'Follow-up email', description: 'A post-application follow-up template.' },
]
const viewFilterOptions: Array<{ label: string; value: OpportunityViewFilter }> = [
  { label: 'All opportunities', value: 'all' },
  { label: 'Best matches', value: 'best' },
  { label: 'Evaluated only', value: 'evaluated' },
  { label: 'Not evaluated', value: 'pending' },
]
const scopeFilterOptions: Array<{ label: string; value: OpportunityScopeFilter }> = [
  { label: 'Default list', value: 'default' },
  { label: 'Include hidden / low relevance', value: 'include_hidden' },
  { label: 'Show duplicates by path', value: 'show_duplicates' },
  { label: 'Full review mode', value: 'expanded' },
]

const includeHidden = computed(() => scopeFilter.value === 'include_hidden' || scopeFilter.value === 'expanded')
const showDuplicates = computed(() => scopeFilter.value === 'show_duplicates' || scopeFilter.value === 'expanded')
const evaluatedCount = computed(() => opportunities.value.filter((opportunity) => isEvaluated(opportunity)).length)
const pendingCount = computed(() => opportunities.value.filter((opportunity) => !isEvaluated(opportunity)).length)
const bestMatchCount = computed(() => opportunities.value.filter((opportunity) => isFitOpportunity(opportunity)).length)
const selectedDescriptionBlocks = computed(() => selectedOpportunity.value ? descriptionBlocks(selectedOpportunity.value) : [])
const activeViewLabel = computed(() => viewFilterOptions.find((option) => option.value === viewFilter.value)?.label || 'All opportunities')
const hasActiveFilters = computed(() => query.value.trim() !== '' || viewFilter.value !== 'all' || scopeFilter.value !== 'default')

const filteredOpportunities = computed(() => {
  const search = debouncedQuery.value.trim().toLowerCase()
  let pool = opportunities.value

  if (viewFilter.value === 'best') {
    pool = pool.filter((opportunity) => isFitOpportunity(opportunity))
  } else if (viewFilter.value === 'evaluated') {
    pool = pool.filter((opportunity) => isEvaluated(opportunity))
  } else if (viewFilter.value === 'pending') {
    pool = pool.filter((opportunity) => !isEvaluated(opportunity))
  }

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

async function resetFilters(): Promise<void> {
  const needsReload = scopeFilter.value !== 'default'

  query.value = ''
  viewFilter.value = 'all'
  scopeFilter.value = 'default'

  if (needsReload) {
    await loadOpportunities()
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

async function collectJobs(): Promise<void> {
  collecting.value = true

  try {
    const response = await collectJobsForActivePaths(true)
    const totals = (response.runs || []).reduce(
      (carry, run) => ({
        fetched: carry.fetched + run.fetched_count,
        accepted: carry.accepted + run.accepted_count,
        filtered: carry.filtered + run.filtered_count,
        failed: carry.failed + run.failed_count,
      }),
      { fetched: 0, accepted: 0, filtered: 0, failed: 0 },
    )

    toast.add({
      severity: totals.failed > 0 ? 'warn' : 'success',
      summary: 'Job collection finished',
      detail: `${response.processed} paths processed. ${totals.accepted}/${totals.fetched} jobs accepted, ${totals.filtered} filtered out.`,
      life: 5000,
    })

    await refreshList()
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Collection failed', detail: getApiErrorMessage(error), life: 5000 })
  } finally {
    collecting.value = false
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

async function viewPackage(opportunity: JobOpportunity): Promise<void> {
  if (!opportunity.apply_package_id) {
    return
  }

  loadingPackageOpportunityId.value = opportunity.id

  try {
    selectedApplyPackage.value = await getApplyPackage(opportunity.apply_package_id)
    selectedOpportunity.value = opportunity
    applyPackageVisible.value = true
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Package unavailable', detail: getApiErrorMessage(error), life: 5000 })
  } finally {
    loadingPackageOpportunityId.value = null
  }
}

function openPackageOptions(opportunity: JobOpportunity): void {
  packageOptionsOpportunity.value = opportunity
  selectedPackageSections.value = [
    'tailored_resume',
    'cover_letter',
    'application_answers',
    'salary_answer',
    'notice_period_answer',
    'interest_answer',
    'strengths_gaps',
    'interview_questions',
    'follow_up_email',
  ]
  packageOptionsVisible.value = true
}

async function confirmCreatePackage(): Promise<void> {
  if (!packageOptionsOpportunity.value) {
    return
  }

  await createPackage(packageOptionsOpportunity.value, selectedPackageSections.value)
}

async function createPackage(opportunity: JobOpportunity, sections: ApplyPackageSection[]): Promise<void> {
  if (!isEvaluated(opportunity)) {
    toast.add({
      severity: 'warn',
      summary: 'Evaluate fit first',
      detail: 'Run evaluation first so the package can use the score, reasons, and gaps.',
      life: 4000,
    })
    return
  }

  const continuingAnyway = isLowFitOpportunity(opportunity)
  generatingPackageOpportunityId.value = opportunity.id

  try {
    const applyPackage = await generateApplyPackage(opportunity.job_id, {
      career_profile_id: opportunity.career_profile_id ?? opportunity.match?.candidate_profile_id ?? opportunity.match?.profile_id ?? null,
      job_path_id: opportunity.job_path_id ?? opportunity.match?.job_path_id ?? null,
      sections,
      override_low_match: continuingAnyway,
      continue_anyway: continuingAnyway,
      override_reason: continuingAnyway
        ? 'User chose to continue after the opportunity did not pass the match threshold.'
        : null,
    })

    selectedOpportunity.value = opportunity
    selectedApplyPackage.value = applyPackage
    const updatedOpportunity: JobOpportunity = {
      ...opportunity,
      apply_package_id: applyPackage.id,
      apply_package: {
        id: applyPackage.id,
        status: applyPackage.status,
        application_id: applyPackage.application_id ?? null,
        resume_id: applyPackage.resume_id ?? null,
        created_at: applyPackage.created_at ?? null,
        updated_at: applyPackage.updated_at ?? null,
      },
    }
    replaceOpportunity(updatedOpportunity)
    selectedOpportunity.value = updatedOpportunity
    packageOptionsVisible.value = false
    applyPackageVisible.value = true

    toast.add({
      severity: 'success',
      summary: 'Apply package ready',
      detail: continuingAnyway
        ? 'Package created with a low-fit override. Review the gaps before applying.'
        : 'Resume, cover letter, answers, and follow-up content were prepared.',
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
    if (selectedOpportunity.value?.apply_package_id === selectedApplyPackage.value.id) {
      const updatedOpportunity: JobOpportunity = {
        ...selectedOpportunity.value,
        apply_package: {
          ...(selectedOpportunity.value.apply_package ?? { id: selectedApplyPackage.value.id }),
          status: 'used',
          application_id: application.id,
        },
      }
      selectedOpportunity.value = updatedOpportunity
      replaceOpportunity(updatedOpportunity)
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
  if (!isEvaluated(opportunity)) {
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

function canCreateApplyPackage(opportunity: JobOpportunity): boolean {
  return isEvaluated(opportunity) && !hasApplyPackage(opportunity)
}

function isLowFitOpportunity(opportunity: JobOpportunity): boolean {
  return isEvaluated(opportunity) && !isFitOpportunity(opportunity)
}

function isEvaluated(opportunity: JobOpportunity): boolean {
  return Boolean(opportunity.is_evaluated || opportunity.match_id || opportunity.evaluated_at)
}

function hasApplyPackage(opportunity: JobOpportunity): boolean {
  return Boolean(opportunity.apply_package_id || opportunity.apply_package?.id)
}

function matchThreshold(opportunity: JobOpportunity): number {
  return opportunity.thresholds?.min_match_score ?? opportunity.job_path?.min_match_score ?? 75
}

function evaluatedMatchScore(opportunity: JobOpportunity): number {
  return normalizeScore(opportunity.match_score ?? opportunity.match?.overall_score)
}

function normalizeScore(value?: number | null): number {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    return 0
  }

  return value <= 1 ? Math.round(value * 100) : Math.round(value)
}

function applyPackageActionLabel(opportunity: JobOpportunity): string {
  return isLowFitOpportunity(opportunity) ? 'Continue Anyway' : 'Create Apply Package'
}

function applyPackageActionIcon(opportunity: JobOpportunity): string {
  return isLowFitOpportunity(opportunity) ? 'pi pi-exclamation-triangle' : 'pi pi-file-edit'
}

function applyPackageActionSeverity(opportunity: JobOpportunity): 'success' | 'warn' {
  return isLowFitOpportunity(opportunity) ? 'warn' : 'success'
}

function evaluationStatusLabel(opportunity: JobOpportunity): string {
  return isFitOpportunity(opportunity) ? 'Fit evaluated' : 'Low fit'
}

function evaluationStatusIcon(opportunity: JobOpportunity): string {
  return isFitOpportunity(opportunity) ? 'pi pi-check-circle' : 'pi pi-exclamation-triangle'
}

function evaluationStatusSeverity(opportunity: JobOpportunity): 'success' | 'warn' {
  return isFitOpportunity(opportunity) ? 'success' : 'warn'
}

function nextActionText(opportunity: JobOpportunity): string {
  if (!isEvaluated(opportunity)) {
    return 'Run Evaluate Fit only if this job looks worth deeper AI analysis.'
  }

  if (hasApplyPackage(opportunity)) {
    return opportunity.apply_package?.application_id
      ? 'This opportunity already has an apply package and an application in your tracker.'
      : 'This opportunity already has an apply package. Open it to copy content, download the resume, or create an application.'
  }

  if (isFitOpportunity(opportunity)) {
    return 'This job passed your threshold. Create an apply package with a tailored CV, cover letter, answers, and follow-up email.'
  }

  return 'This job was evaluated but did not pass your apply threshold. You can hide it, keep it in history, or continue anyway if you still want to apply.'
}

function packageContinuedAnyway(applyPackage: ApplyPackage): boolean {
  return Boolean(applyPackage.metadata?.override_low_match || applyPackage.metadata?.continue_anyway)
}

function descriptionBlocks(opportunity: JobOpportunity): DescriptionBlock[] {
  const rawDescription = opportunity.job?.description_raw || ''

  if (hasHtmlDescription(rawDescription)) {
    const htmlBlocks = htmlDescriptionBlocks(rawDescription)

    if (htmlBlocks.length > 0) {
      return htmlBlocks
    }
  }

  const text = normalizeDescriptionText(opportunity.job?.description_clean || rawDescription)

  if (!text) {
    return []
  }

  return textDescriptionBlocks(text)
}

function textDescriptionBlocks(text: string): DescriptionBlock[] {
  const blocks: DescriptionBlock[] = []
  const listItems: string[] = []
  let listOrdered = false
  const flushList = () => {
    if (listItems.length > 0) {
      blocks.push({ type: 'list', ordered: listOrdered, items: [...listItems] })
      listItems.length = 0
      listOrdered = false
    }
  }

  for (const line of text.split('\n').map((item) => item.trim()).filter(Boolean)) {
    const bullet = line.match(/^([-*]|\u2022|\d+[.)])\s+(.+)$/)

    const parsedBullet = bullet ?? line.match(/^(\u2022)\s+(.+)$/)

    if (parsedBullet?.[2]) {
      const ordered = /^\d+[.)]$/.test(parsedBullet[1])

      if (listItems.length > 0 && ordered !== listOrdered) {
        flushList()
      }

      listOrdered = ordered
      listItems.push(parsedBullet[2].trim())
      continue
    }

    flushList()

    if (isDescriptionHeading(line)) {
      blocks.push({ type: 'heading', text: line.replace(/:$/, '') })
    } else {
      blocks.push({ type: 'paragraph', text: line })
    }
  }

  flushList()

  return blocks
}

function htmlDescriptionBlocks(value: string): DescriptionBlock[] {
  if (typeof DOMParser === 'undefined') {
    return []
  }

  const document = new DOMParser().parseFromString(value, 'text/html')
  const blocks: DescriptionBlock[] = []

  appendHtmlChildren(document.body, blocks)

  return mergeAdjacentDescriptionBlocks(blocks)
}

function appendHtmlChildren(parent: Element, blocks: DescriptionBlock[]): void {
  parent.childNodes.forEach((node) => appendHtmlNode(node, blocks))
}

function appendHtmlNode(node: ChildNode, blocks: DescriptionBlock[]): void {
  if (node.nodeType === 3) {
    const text = cleanDescriptionText(node.textContent || '')

    if (text) {
      blocks.push({ type: 'paragraph', text })
    }

    return
  }

  if (node.nodeType !== 1) {
    return
  }

  const element = node as HTMLElement
  const tag = element.tagName.toLowerCase()

  if (['script', 'style', 'noscript'].includes(tag)) {
    return
  }

  const text = cleanDescriptionText(element.textContent || '')

  if (!text && tag !== 'br') {
    return
  }

  if (/^h[1-6]$/.test(tag)) {
    blocks.push({ type: 'heading', text })
    return
  }

  if (tag === 'ul' || tag === 'ol') {
    const items = Array.from(element.children)
      .filter((child) => child.tagName.toLowerCase() === 'li')
      .map((child) => cleanDescriptionText(child.textContent || ''))
      .filter(Boolean)

    if (items.length > 0) {
      blocks.push({ type: 'list', ordered: tag === 'ol', items })
    }

    return
  }

  if (tag === 'li') {
    blocks.push({ type: 'list', ordered: false, items: [text] })
    return
  }

  if (hasBlockChildren(element)) {
    appendHtmlChildren(element, blocks)
    return
  }

  if (['p', 'div', 'section', 'article', 'span', 'strong', 'em'].includes(tag)) {
    blocks.push(isDescriptionHeading(text)
      ? { type: 'heading', text: text.replace(/:$/, '') }
      : { type: 'paragraph', text })
  }
}

function mergeAdjacentDescriptionBlocks(blocks: DescriptionBlock[]): DescriptionBlock[] {
  const merged: DescriptionBlock[] = []

  for (const block of blocks) {
    const previous = merged.at(-1)

    if (block.type === 'list' && previous?.type === 'list' && previous.ordered === block.ordered) {
      previous.items.push(...block.items)
      continue
    }

    if (block.type === 'paragraph' && previous?.type === 'paragraph' && previous.text === block.text) {
      continue
    }

    merged.push(block)
  }

  return merged
}

function hasBlockChildren(element: HTMLElement): boolean {
  return Array.from(element.children).some((child) => /^(h[1-6]|p|div|section|article|ul|ol|li)$/i.test(child.tagName))
}

function hasHtmlDescription(value: string): boolean {
  return /<\/?[a-z][\s\S]*>/i.test(value)
}

function cleanDescriptionText(value: string): string {
  return value
    .replace(/\u00a0/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

function normalizeDescriptionText(value: string): string {
  return value
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/(p|div|li|h[1-6])>/gi, '\n')
    .replace(/<li[^>]*>/gi, '\n- ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;/gi, "'")
    .replace(/\r/g, '\n')
    .replace(/[ \t]+/g, ' ')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

function isDescriptionHeading(line: string): boolean {
  const normalized = line.toLowerCase().replace(/:$/, '').trim()
  const knownHeadings = [
    'about',
    'about the role',
    'about this role',
    'responsibilities',
    'requirements',
    'qualifications',
    'required skills',
    'preferred skills',
    'nice to have',
    'what you will do',
    'what you will bring',
    'benefits',
    'salary',
    'location',
    'job description',
  ]

  if (knownHeadings.includes(normalized)) {
    return true
  }

  if (line.endsWith(':') && line.length <= 90) {
    return true
  }

  return line.length <= 70
    && !/[.!?]$/.test(line)
    && /(responsibilit|requirement|qualification|benefit|skill|experience|about|role|team|location|salary)/i.test(line)
}

function openExternalUrl(url?: string | null): void {
  if (!url) {
    return
  }

  window.open(url, '_blank', 'noopener,noreferrer')
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
