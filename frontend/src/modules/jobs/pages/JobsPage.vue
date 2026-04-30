<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Job Management"
      title="Jobs"
      description="Review ingested jobs, run analysis and matching, and generate tailored resumes from the same workspace."
    />

    <ErrorState v-if="errorMessage" title="Jobs unavailable" :message="errorMessage">
      <template #actions>
        <Button label="Retry" icon="pi pi-refresh" @click="loadJobs" />
        <Button label="Go to Job Sources" icon="pi pi-database" severity="secondary" outlined @click="router.push('/job-sources')" />
      </template>
    </ErrorState>

    <SkeletonTable v-if="loading" :columns="6" />

    <div v-else class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <IconField class="w-full lg:max-w-sm">
          <InputIcon class="pi pi-search" />
          <InputText v-model.trim="query" fluid placeholder="Search by title, company, or location" />
        </IconField>

        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
          Showing {{ filteredJobs.length }} of {{ totalJobs }} jobs
        </div>
      </div>

      <EmptyState
        v-if="filteredJobs.length === 0"
        title="No jobs available"
        description="Ingest jobs from the Job Sources page to start analysis, matching, and resume generation."
        icon="pi-briefcase"
      >
        <template #actions>
          <Button label="Go to Job Sources" icon="pi pi-database" @click="router.push('/job-sources')" />
        </template>
      </EmptyState>

      <DataTable
        v-else
        :value="filteredJobs"
        data-key="id"
        paginator
        :rows="10"
        class="mt-6"
        striped-rows
        responsive-layout="scroll"
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
              <p>{{ data.location || 'N/A' }}</p>
              <Tag :severity="data.is_remote ? 'success' : 'contrast'" :value="data.is_remote ? 'Remote' : 'On-site'" />
            </div>
          </template>
        </Column>

        <Column field="status" header="Status">
          <template #body="{ data }">
            <Tag :severity="statusSeverity(data.status)" :value="data.status || 'unknown'" />
          </template>
        </Column>

        <Column header="Insights">
          <template #body="{ data }">
            <div class="space-y-2">
              <div class="flex flex-wrap gap-2">
                <Tag
                  v-if="data.analysis?.workplace_type"
                  severity="contrast"
                  :value="formatCategoryLabel(data.analysis.workplace_type)"
                />
                <Tag
                  v-if="data.analysis?.years_experience_min || data.analysis?.years_experience_max"
                  severity="secondary"
                  :value="formatExperienceRange(data.analysis)"
                />
                <Tag
                  v-if="data.analysis?.timezone_hint"
                  severity="info"
                  :value="data.analysis.timezone_hint"
                />
              </div>
              <div class="flex flex-wrap gap-2">
                <Tag
                  v-for="tech in previewItems(data.analysis?.tech_stack, 3)"
                  :key="`${data.id}-tech-${tech}`"
                  severity="success"
                  :value="tech"
                />
                <span
                  v-if="!data.analysis"
                  class="text-xs text-slate-400"
                >
                  Run analysis to populate insights
                </span>
              </div>
            </div>
          </template>
        </Column>

        <Column field="created_at" header="Created">
          <template #body="{ data }">
            {{ formatDateTime(data.created_at) }}
          </template>
        </Column>

        <Column header="Actions" :style="{ width: '24rem' }">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-2">
              <Button
                label="View Details"
                icon="pi pi-eye"
                size="small"
                text
                @click="openDetails(data.id)"
              />
              <Button
                label="Analyze"
                icon="pi pi-chart-line"
                size="small"
                severity="secondary"
                outlined
                :loading="actionLoading.analyze === data.id"
                @click="handleAnalyze(data.id)"
              />
              <Button
                label="Match"
                icon="pi pi-star"
                size="small"
                severity="warn"
                outlined
                :loading="actionLoading.match === data.id"
                @click="startProfileAction('match', data)"
              />
              <Button
                label="Generate Resume"
                icon="pi pi-file-edit"
                size="small"
                severity="help"
                outlined
                :loading="actionLoading.resume === data.id"
                @click="startProfileAction('resume', data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Dialog v-model:visible="detailsDialogVisible" modal header="Job Details" :style="{ width: '70rem' }">
      <div v-if="detailsLoading" class="flex items-center justify-center py-16">
        <ProgressSpinner stroke-width="4" />
      </div>

      <div v-else-if="selectedJob" class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <h3 class="text-2xl font-semibold text-slate-900">{{ selectedJob.title }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ selectedJob.company_name || 'Unknown company' }}</p>
          </div>

          <div class="flex flex-wrap gap-2">
            <Tag :severity="statusSeverity(selectedJob.status)" :value="selectedJob.status || 'unknown'" />
            <Tag :severity="selectedJob.is_remote ? 'success' : 'contrast'" :value="selectedJob.is_remote ? 'Remote' : 'On-site'" />
          </div>
        </div>

        <div class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Location</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedJob.location || 'N/A' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Employment Type</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedJob.employment_type || 'N/A' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Remote Type</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedJob.remote_type || 'N/A' }}</p>
          </div>
          <div class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Posted</p>
            <p class="mt-1 font-medium text-slate-900">{{ formatDateTime(selectedJob.posted_at) }}</p>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h4 class="text-lg font-semibold text-slate-900">Job URL</h4>
            <Button
              v-if="selectedJob.url"
              label="Open URL"
              icon="pi pi-external-link"
              size="small"
              text
              @click="openUrl(selectedJob.url)"
            />
            <Button
              label="Re-analyze AI"
              icon="pi pi-refresh"
              size="small"
              severity="secondary"
              outlined
              :loading="actionLoading.analyze === selectedJob.id"
              @click="handleAnalyze(selectedJob.id, true)"
            />
          </div>
          <p class="break-all text-sm text-sky-700">{{ selectedJob.url || 'N/A' }}</p>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">Description</h4>
          <pre class="whitespace-pre-wrap break-words font-sans text-sm leading-6 text-slate-700">{{ jobDescription }}</pre>
        </div>

        <div v-if="selectedJob.analysis" class="rounded-3xl border border-emerald-200 bg-emerald-50 p-4">
          <h4 class="mb-4 text-lg font-semibold text-slate-900">Analysis</h4>
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <p class="mb-2 text-sm font-medium text-slate-700">Required Skills</p>
              <div class="flex flex-wrap gap-2">
                <Tag v-for="skill in selectedJob.analysis.required_skills || []" :key="`required-${skill}`" :value="skill" severity="success" />
              </div>
            </div>
            <div>
              <p class="mb-2 text-sm font-medium text-slate-700">Preferred Skills</p>
              <div class="flex flex-wrap gap-2">
                <Tag v-for="skill in selectedJob.analysis.preferred_skills || []" :key="`preferred-${skill}`" :value="skill" severity="info" />
              </div>
            </div>
            <div>
              <p class="mb-2 text-sm font-medium text-slate-700">Must-Have Skills</p>
              <div class="flex flex-wrap gap-2">
                <Tag v-for="skill in selectedJob.analysis.must_have_skills || []" :key="`must-${skill}`" :value="skill" severity="danger" />
              </div>
            </div>
            <div>
              <p class="mb-2 text-sm font-medium text-slate-700">Nice-To-Have Skills</p>
              <div class="flex flex-wrap gap-2">
                <Tag v-for="skill in selectedJob.analysis.nice_to_have_skills || []" :key="`nice-${skill}`" :value="skill" severity="warn" />
              </div>
            </div>
            <div class="rounded-2xl bg-white px-4 py-3">
              <p class="text-slate-500">Seniority</p>
              <p class="mt-1 font-medium text-slate-900">{{ selectedJob.analysis.seniority || 'N/A' }}</p>
            </div>
            <div class="rounded-2xl bg-white px-4 py-3">
              <p class="text-slate-500">Role Type</p>
              <p class="mt-1 font-medium text-slate-900">{{ selectedJob.analysis.role_type || 'N/A' }}</p>
            </div>
            <div class="rounded-2xl bg-white px-4 py-3">
              <p class="text-slate-500">Confidence Score</p>
              <p class="mt-1 font-medium text-slate-900">{{ selectedJob.analysis.confidence_score ?? 0 }}%</p>
            </div>
            <div class="rounded-2xl bg-white px-4 py-3">
              <p class="text-slate-500">AI Provider</p>
              <p class="mt-1 font-medium text-slate-900">{{ selectedJob.analysis.ai_provider || 'Deterministic fallback' }}</p>
            </div>
            <div class="rounded-2xl bg-white px-4 py-3">
              <p class="text-slate-500">Years of Experience</p>
              <p class="mt-1 font-medium text-slate-900">{{ formatExperienceRange(selectedJob.analysis) }}</p>
            </div>
            <div class="rounded-2xl bg-white px-4 py-3">
              <p class="text-slate-500">Workplace Type</p>
              <p class="mt-1 font-medium text-slate-900">{{ selectedJob.analysis.workplace_type || 'N/A' }}</p>
            </div>
            <div class="rounded-2xl bg-white px-4 py-3">
              <p class="text-slate-500">Salary</p>
              <p class="mt-1 font-medium text-slate-900">{{ selectedJob.analysis.salary_text || 'N/A' }}</p>
            </div>
            <div class="rounded-2xl bg-white px-4 py-3">
              <p class="text-slate-500">Timezone</p>
              <p class="mt-1 font-medium text-slate-900">{{ selectedJob.analysis.timezone_hint || 'N/A' }}</p>
            </div>
            <div class="rounded-2xl bg-white px-4 py-3">
              <p class="text-slate-500">Location Hint</p>
              <p class="mt-1 font-medium text-slate-900">{{ selectedJob.analysis.location_hint || 'N/A' }}</p>
            </div>
          </div>

          <div class="mt-4">
            <p class="mb-2 text-sm font-medium text-slate-700">Domain Tags</p>
            <div class="flex flex-wrap gap-2">
              <Tag v-for="tag in selectedJob.analysis.domain_tags || []" :key="tag" :value="tag" severity="warn" />
            </div>
          </div>

          <div class="mt-4">
            <p class="mb-2 text-sm font-medium text-slate-700">Tech Stack</p>
            <div class="flex flex-wrap gap-2">
              <Tag v-for="tech in selectedJob.analysis.tech_stack || []" :key="tech" :value="tech" severity="contrast" />
            </div>
          </div>

          <div
            v-if="analysisSkillCategoryEntries.length > 0"
            class="mt-4 rounded-2xl bg-white px-4 py-3"
          >
            <p class="mb-3 text-sm font-medium text-slate-700">Skill Categories</p>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <div
                v-for="[category, skills] in analysisSkillCategoryEntries"
                :key="category"
                class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
              >
                <p class="mb-2 text-sm font-medium capitalize text-slate-800">{{ formatCategoryLabel(category) }}</p>
                <div class="flex flex-wrap gap-2">
                  <Tag v-for="skill in skills" :key="`${category}-${skill}`" :value="skill" severity="info" />
                </div>
              </div>
            </div>
          </div>

          <div v-if="(selectedJob.analysis.responsibilities || []).length > 0" class="mt-4 rounded-2xl bg-white px-4 py-3">
            <p class="mb-2 text-sm font-medium text-slate-700">Responsibilities</p>
            <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
              <li v-for="responsibility in selectedJob.analysis.responsibilities || []" :key="responsibility">{{ responsibility }}</li>
            </ul>
          </div>

          <div v-if="selectedJob.analysis.company_context" class="mt-4 rounded-2xl bg-white px-4 py-3 text-sm leading-6 text-slate-700">
            <p class="mb-2 text-sm font-medium text-slate-700">Company Context</p>
            {{ selectedJob.analysis.company_context }}
          </div>

          <div v-if="selectedJob.analysis.ai_summary" class="mt-4 rounded-2xl bg-white px-4 py-3 text-sm leading-6 text-slate-700">
            {{ selectedJob.analysis.ai_summary }}
          </div>

          <div class="mt-4 rounded-2xl bg-white px-4 py-3">
            <p class="mb-3 text-sm font-medium text-slate-700">AI Metadata</p>
            <div class="grid gap-3 text-sm text-slate-700 md:grid-cols-2 xl:grid-cols-3">
              <p><span class="font-medium text-slate-900">Provider:</span> {{ selectedJob.analysis.ai_provider || 'Deterministic fallback' }}</p>
              <p><span class="font-medium text-slate-900">Model:</span> {{ selectedJob.analysis.ai_model || 'N/A' }}</p>
              <p><span class="font-medium text-slate-900">Prompt:</span> {{ selectedJob.analysis.prompt_version || 'N/A' }}</p>
              <p><span class="font-medium text-slate-900">Duration:</span> {{ formatDuration(selectedJob.analysis.ai_duration_ms) }}</p>
              <p><span class="font-medium text-slate-900">Fallback:</span> {{ yesNo(selectedJob.analysis.fallback_used) }}</p>
              <p><span class="font-medium text-slate-900">Generated At:</span> {{ formatDateTime(selectedJob.analysis.ai_generated_at) }}</p>
            </div>
          </div>
        </div>

        <div v-else class="rounded-3xl border border-dashed border-slate-300 bg-white px-5 py-6 text-sm text-slate-600">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <p class="font-medium text-slate-900">No analysis has been generated for this job yet.</p>
              <p class="mt-1">Run AI analysis to extract skill requirements, workplace signals, salary hints, and structured metadata.</p>
            </div>
            <Button
              label="Analyze Now"
              icon="pi pi-chart-line"
              :loading="actionLoading.analyze === selectedJob.id"
              @click="handleAnalyze(selectedJob.id)"
            />
          </div>
        </div>

        <div v-if="selectedJob.raw_payload" class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h4 class="text-lg font-semibold text-slate-900">Raw Payload</h4>
            <Button label="Copy JSON" icon="pi pi-copy" size="small" text @click="copyRawPayload" />
          </div>
          <pre class="overflow-auto whitespace-pre-wrap break-words rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ rawPayloadText }}</pre>
        </div>
      </div>
    </Dialog>

    <Dialog v-model:visible="profileDialogVisible" modal :header="profileDialogTitle" :style="{ width: '34rem' }">
      <div class="space-y-4">
        <div v-if="profilesLoading" class="flex items-center justify-center py-12">
          <ProgressSpinner stroke-width="4" />
        </div>

        <div v-else-if="profileSelectionState === 'none'" class="space-y-4">
          <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            No candidate profiles are available yet. Create one first before running match or resume generation.
          </div>
          <div class="flex justify-end">
            <Button label="Go to Candidate Profile" icon="pi pi-user-plus" @click="goToCandidateProfiles" />
          </div>
        </div>

        <div v-else class="space-y-4">
          <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
            Select which candidate profile should be used for this job action.
          </div>

          <Select
            v-model="selectedProfileId"
            :options="profiles"
            option-label="headline"
            option-value="id"
            fluid
            placeholder="Choose a candidate profile"
          >
            <template #option="{ option }">
              <div>
                <p class="font-medium text-slate-900">{{ option.headline || option.full_name || `Profile #${option.id}` }}</p>
                <p class="text-sm text-slate-500">{{ option.full_name || 'Unnamed profile' }}</p>
              </div>
            </template>
            <template #value="{ value }">
              <span v-if="value">{{ selectedProfileLabel }}</span>
              <span v-else class="text-slate-400">Choose a candidate profile</span>
            </template>
          </Select>

          <div class="flex justify-end gap-3">
            <Button label="Cancel" severity="secondary" text @click="profileDialogVisible = false" />
            <LoadingButton
              :loading="pendingActionType === 'match' ? actionLoading.match === pendingJobId : actionLoading.resume === pendingJobId"
              :label="pendingActionType === 'match' ? 'Run Match' : 'Generate Resume'"
              loading-label="Processing..."
              @click="confirmProfileAction"
            />
          </div>
        </div>
      </div>
    </Dialog>

    <Dialog v-model:visible="matchDialogVisible" modal header="Match Result" :style="{ width: '44rem' }">
      <div v-if="latestMatch" class="space-y-5">
        <div class="grid gap-4 md:grid-cols-[9rem_minmax(0,1fr)] md:items-center">
          <div class="text-center">
            <Knob :model-value="normalizeScore(latestMatch.overall_score)" :max="100" readonly value-template="{value}%" />
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-3">
              <h3 class="text-2xl font-semibold text-slate-900">Overall Match</h3>
              <Tag :severity="recommendationSeverity(latestMatch.recommendation)" :value="latestMatch.recommendation || 'unknown'" />
              <Tag
                v-if="latestMatch.recommendation_action"
                :severity="recommendationActionSeverity(latestMatch.recommendation_action)"
                :value="decisionLabel(latestMatch.recommendation_action)"
              />
            </div>
            <p class="mt-2 text-sm leading-6 text-slate-600">{{ latestMatch.notes || 'No additional notes were returned.' }}</p>
          </div>
        </div>

        <div class="flex justify-end">
          <Button
            v-if="latestMatch.job_id && latestMatch.profile_id"
            label="Re-run Match"
            icon="pi pi-refresh"
            severity="secondary"
            outlined
            :loading="actionLoading.match === latestMatch.job_id"
            @click="rerunLatestMatch"
          />
        </div>

        <div class="space-y-4">
          <div v-for="metric in matchMetrics" :key="metric.label">
            <div class="mb-2 flex items-center justify-between text-sm">
              <span class="font-medium text-slate-700">{{ metric.label }}</span>
              <span class="text-slate-500">{{ metric.value }}%</span>
            </div>
            <ProgressBar :value="metric.value" />
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
          <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-4">
            <h4 class="mb-2 text-lg font-semibold text-slate-900">Why Suitable?</h4>
            <p class="text-sm leading-6 text-slate-700">{{ latestMatch.why_matched || latestMatch.notes || 'No explicit fit summary available.' }}</p>
          </div>

          <div class="rounded-3xl border border-amber-200 bg-amber-50 p-4">
            <h4 class="mb-2 text-lg font-semibold text-slate-900">What Is Missing?</h4>
            <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
              <li v-for="item in latestMatch.missing_required_skills || latestMatch.missing_skills || []" :key="item">{{ item }}</li>
              <li v-if="!(latestMatch.missing_required_skills?.length || latestMatch.missing_skills?.length)">No required skill gaps were flagged.</li>
            </ul>
          </div>

          <div class="rounded-3xl border border-sky-200 bg-sky-50 p-4">
            <h4 class="mb-2 text-lg font-semibold text-slate-900">Should I Apply?</h4>
            <div class="space-y-3">
              <Tag
                v-if="latestMatch.recommendation_action"
                :severity="recommendationActionSeverity(latestMatch.recommendation_action)"
                :value="decisionLabel(latestMatch.recommendation_action)"
              />
              <p class="text-sm leading-6 text-slate-700">{{ applySummary(latestMatch) }}</p>
            </div>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Why Matched</h4>
            <p class="text-sm leading-6 text-slate-700">{{ latestMatch.why_matched || latestMatch.ai_recommendation_summary || 'No AI explanation available.' }}</p>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Recommendation Summary</h4>
            <p class="text-sm leading-6 text-slate-700">{{ latestMatch.ai_recommendation_summary || latestMatch.notes || 'No summary available.' }}</p>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Strength Areas</h4>
            <div class="flex flex-wrap gap-2">
              <Tag v-for="item in latestMatch.strength_areas || []" :key="item" :value="item" severity="success" />
            </div>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Missing Skills</h4>
            <div class="flex flex-wrap gap-2">
              <Tag v-for="item in latestMatch.missing_required_skills || latestMatch.missing_skills || []" :key="item" :value="item" severity="danger" />
            </div>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Nice-To-Have Gaps</h4>
            <div class="flex flex-wrap gap-2">
              <Tag v-for="item in latestMatch.nice_to_have_gaps || []" :key="item" :value="item" severity="warn" />
            </div>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Risk Flags</h4>
            <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
              <li v-for="item in latestMatch.risk_flags || []" :key="item">{{ item }}</li>
            </ul>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Resume Focus Points</h4>
            <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
              <li v-for="item in latestMatch.resume_focus_points || []" :key="item">{{ item }}</li>
            </ul>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">AI Metadata</h4>
          <div class="grid gap-3 text-sm text-slate-700 md:grid-cols-2 xl:grid-cols-3">
            <p><span class="font-medium text-slate-900">Provider:</span> {{ latestMatch.ai_provider || 'Deterministic fallback' }}</p>
            <p><span class="font-medium text-slate-900">Model:</span> {{ latestMatch.ai_model || 'N/A' }}</p>
            <p><span class="font-medium text-slate-900">Prompt:</span> {{ latestMatch.prompt_version || 'N/A' }}</p>
            <p><span class="font-medium text-slate-900">Duration:</span> {{ formatDuration(latestMatch.ai_duration_ms) }}</p>
            <p><span class="font-medium text-slate-900">Fallback:</span> {{ yesNo(latestMatch.fallback_used) }}</p>
            <p><span class="font-medium text-slate-900">Generated At:</span> {{ formatDateTime(latestMatch.ai_generated_at) }}</p>
          </div>
        </div>
      </div>
    </Dialog>

    <Dialog v-model:visible="resumeDialogVisible" modal header="Resume Preview" :style="{ width: '60rem' }">
      <div v-if="generatedResume" class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <h3 class="text-2xl font-semibold text-slate-900">{{ generatedResume.headline || 'Generated Resume' }}</h3>
            <p class="mt-1 text-sm text-slate-500">Version {{ generatedResume.version_name || 'v1' }}</p>
          </div>

          <div class="flex flex-wrap gap-2">
            <Button
              label="Regenerate"
              icon="pi pi-refresh"
              severity="secondary"
              outlined
              :loading="generatedResume ? actionLoading.resume === generatedResume.job_id : false"
              @click="rerunGeneratedResume"
            />
            <Button
              label="Create Application"
              icon="pi pi-send"
              :loading="creatingApplication"
              @click="handleCreateApplication"
            />
            <Button
              v-if="generatedResume.download_pdf_url || generatedResume.pdf_url"
              label="Download PDF"
              icon="pi pi-download"
              severity="secondary"
              outlined
              :loading="downloadingGeneratedResumePdf"
              @click="handleDownloadGeneratedResumePdf"
            />
            <Button
              v-if="resumePreviewUrl"
              label="Open Preview"
              icon="pi pi-external-link"
              severity="secondary"
              outlined
              @click="openUrl(resumePreviewUrl)"
            />
            <Button
              label="Copy Content"
              icon="pi pi-copy"
              severity="secondary"
              outlined
              @click="copyResumeContent"
            />
          </div>
        </div>

        <div
          v-if="currentResumeMatch?.recommendation_action"
          :class="[
            'rounded-3xl border p-4',
            currentResumeMatch.recommendation_action === 'apply'
              ? 'border-emerald-200 bg-emerald-50'
              : currentResumeMatch.recommendation_action === 'consider'
                ? 'border-amber-200 bg-amber-50'
                : 'border-rose-200 bg-rose-50',
          ]"
        >
          <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <div class="flex flex-wrap items-center gap-3">
                <h4 class="text-lg font-semibold text-slate-900">Application Decision</h4>
                <Tag
                  :severity="recommendationActionSeverity(currentResumeMatch.recommendation_action)"
                  :value="decisionLabel(currentResumeMatch.recommendation_action)"
                />
              </div>
              <p class="mt-2 text-sm leading-6 text-slate-700">{{ applySummary(currentResumeMatch) }}</p>
            </div>
          </div>
        </div>

        <div v-if="generatedResume.professional_summary" class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-2 text-lg font-semibold text-slate-900">Professional Summary</h4>
          <p class="text-sm leading-6 text-slate-700">{{ generatedResume.professional_summary }}</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">Selected Skills</h4>
            <div class="flex flex-wrap gap-2">
              <Tag v-for="skill in generatedResume.selected_skills || []" :key="skill" :value="skill" severity="success" />
            </div>
          </div>

          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <h4 class="mb-3 text-lg font-semibold text-slate-900">ATS Keywords</h4>
            <div class="flex flex-wrap gap-2">
              <Tag v-for="keyword in generatedResume.ats_keywords || []" :key="keyword" :value="keyword" severity="warn" />
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">Selected Experience Bullets</h4>
          <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
            <li v-for="bullet in generatedResume.selected_experience_bullets || []" :key="bullet">{{ bullet }}</li>
          </ul>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">Selected Projects</h4>
          <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
            <li v-for="project in generatedResume.selected_projects || []" :key="project">{{ project }}</li>
          </ul>
        </div>

        <div v-if="(generatedResume.warnings_or_gaps || []).length > 0" class="rounded-3xl border border-amber-200 bg-amber-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">Warnings / Gaps</h4>
          <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-slate-700">
            <li v-for="warning in generatedResume.warnings_or_gaps || []" :key="warning">{{ warning }}</li>
          </ul>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <h4 class="mb-3 text-lg font-semibold text-slate-900">Stored Output</h4>
          <div class="space-y-2 text-sm text-slate-700">
            <p><span class="font-medium text-slate-900">HTML path:</span> {{ generatedResume.html_path || 'N/A' }}</p>
            <p><span class="font-medium text-slate-900">PDF path:</span> {{ generatedResume.pdf_path || 'Not generated' }}</p>
            <p><span class="font-medium text-slate-900">AI provider:</span> {{ generatedResume.ai_provider || 'Deterministic fallback' }}</p>
            <p><span class="font-medium text-slate-900">AI model:</span> {{ generatedResume.ai_model || 'N/A' }}</p>
            <p><span class="font-medium text-slate-900">Prompt:</span> {{ generatedResume.prompt_version || 'N/A' }}</p>
            <p><span class="font-medium text-slate-900">Duration:</span> {{ formatDuration(generatedResume.ai_duration_ms) }}</p>
            <p><span class="font-medium text-slate-900">Fallback:</span> {{ yesNo(generatedResume.fallback_used) }}</p>
            <p><span class="font-medium text-slate-900">Generated at:</span> {{ formatDateTime(generatedResume.ai_generated_at) }}</p>
            <p><span class="font-medium text-slate-900">AI confidence:</span> {{ generatedResume.ai_confidence_score ?? 0 }}%</p>
          </div>
        </div>
      </div>
    </Dialog>

    <Dialog v-model:visible="applicationGuardDialogVisible" modal header="Confirm Application Creation" :style="{ width: '34rem' }">
      <div class="space-y-4">
        <div
          :class="[
            'rounded-2xl border px-4 py-3 text-sm leading-6',
            applicationDecisionAction === 'skip'
              ? 'border-rose-200 bg-rose-50 text-rose-800'
              : 'border-amber-200 bg-amber-50 text-amber-800',
          ]"
        >
          {{ applicationDecisionMessage }}
        </div>

        <div v-if="currentResumeMatch && (currentResumeMatch.missing_required_skills?.length || currentResumeMatch.nice_to_have_gaps?.length)" class="space-y-3">
          <div v-if="currentResumeMatch.missing_required_skills?.length" class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="mb-2 text-sm font-medium text-slate-900">Missing Required Skills</p>
            <div class="flex flex-wrap gap-2">
              <Tag
                v-for="item in currentResumeMatch.missing_required_skills || []"
                :key="`guard-missing-${item}`"
                severity="danger"
                :value="item"
              />
            </div>
          </div>

          <div v-if="currentResumeMatch.nice_to_have_gaps?.length" class="rounded-2xl bg-slate-50 px-4 py-3">
            <p class="mb-2 text-sm font-medium text-slate-900">Nice-To-Have Gaps</p>
            <div class="flex flex-wrap gap-2">
              <Tag
                v-for="item in currentResumeMatch.nice_to_have_gaps || []"
                :key="`guard-gap-${item}`"
                severity="warn"
                :value="item"
              />
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Button label="Cancel" severity="secondary" text @click="applicationGuardDialogVisible = false" />
          <Button
            :label="applicationDecisionAction === 'skip' ? 'Create Anyway' : 'Continue'"
            icon="pi pi-send"
            :loading="creatingApplication"
            @click="confirmCreateApplication"
          />
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Knob from 'primevue/knob'
import ProgressBar from 'primevue/progressbar'
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'

import EmptyState from '@/shared/components/EmptyState.vue'
import ErrorState from '@/shared/components/ErrorState.vue'
import LoadingButton from '@/shared/components/LoadingButton.vue'
import { createApplication } from '@/modules/applications/services/applicationsApi'
import { downloadResumePdf } from '@/modules/resumes/services/resumesApi'
import PageHeader from '@/shared/components/PageHeader.vue'
import SkeletonTable from '@/shared/components/SkeletonTable.vue'
import { useDebouncedValue } from '@/shared/composables/useDebouncedValue'
import { copyText } from '@/shared/utils/clipboard'
import { getApiErrorMessage, getCollectionTotal } from '@/shared/utils/api'
import {
  analyzeJob,
  generateResume,
  getJob,
  listCandidateProfiles,
  listJobs,
  matchJob,
} from '@/modules/jobs/services/jobsApi'
import type { CandidateProfile, Job, JobMatch, TailoredResume } from '@/modules/jobs/types'

type PendingActionType = 'match' | 'resume' | null

const router = useRouter()
const toast = useToast()

const loading = ref(false)
const detailsLoading = ref(false)
const profilesLoading = ref(false)
const errorMessage = ref('')
const query = ref('')
const debouncedQuery = useDebouncedValue(query, 250)
const jobs = ref<Job[]>([])
const totalJobs = ref(0)
const detailsDialogVisible = ref(false)
const selectedJob = ref<Job | null>(null)
const profileDialogVisible = ref(false)
const matchDialogVisible = ref(false)
const resumeDialogVisible = ref(false)
const latestMatch = ref<JobMatch | null>(null)
const generatedResume = ref<TailoredResume | null>(null)
const generatedResumeProfileId = ref<number | null>(null)
const downloadingGeneratedResumePdf = ref(false)
const creatingApplication = ref(false)
const applicationGuardDialogVisible = ref(false)
const profiles = ref<CandidateProfile[]>([])
const selectedProfileId = ref<number | null>(null)
const pendingActionType = ref<PendingActionType>(null)
const pendingJobId = ref<number | null>(null)
const pendingForce = ref(false)
const profileSelectionState = ref<'idle' | 'none' | 'ready'>('idle')
const actionLoading = ref({
  analyze: null as number | null,
  match: null as number | null,
  resume: null as number | null,
})

const filteredJobs = computed(() => {
  const search = debouncedQuery.value.trim().toLowerCase()

  return jobs.value.filter((job) => {
    if (!search) {
      return true
    }

    return [job.title, job.company_name ?? '', job.location ?? ''].some((value) => value.toLowerCase().includes(search))
  })
})

const jobDescription = computed(() => selectedJob.value?.description_clean || selectedJob.value?.description_raw || 'No description available.')
const rawPayloadText = computed(() => (selectedJob.value?.raw_payload ? JSON.stringify(selectedJob.value.raw_payload, null, 2) : 'No raw payload available.'))
const profileDialogTitle = computed(() => (pendingActionType.value === 'match' ? 'Select Candidate Profile for Match' : 'Select Candidate Profile for Resume'))
const selectedProfileLabel = computed(() => {
  const profile = profiles.value.find((item) => item.id === selectedProfileId.value)
  return profile?.headline || profile?.full_name || ''
})
const matchMetrics = computed(() => {
  if (!latestMatch.value) {
    return []
  }

  return [
    { label: 'Skill Score', value: normalizeScore(latestMatch.value.skill_score) },
    { label: 'Experience Score', value: normalizeScore(latestMatch.value.experience_score) },
    { label: 'Title Score', value: normalizeScore(latestMatch.value.title_score) },
    { label: 'Seniority Score', value: normalizeScore(latestMatch.value.seniority_score) },
    { label: 'Location Score', value: normalizeScore(latestMatch.value.location_score) },
    { label: 'Backend Focus Score', value: normalizeScore(latestMatch.value.backend_focus_score) },
    { label: 'Domain Score', value: normalizeScore(latestMatch.value.domain_score) },
  ]
})
const analysisSkillCategoryEntries = computed(() => {
  const categories = selectedJob.value?.analysis?.skill_categories

  if (!categories) {
    return []
  }

  return Object.entries(categories).filter(([, skills]) => Array.isArray(skills) && skills.length > 0)
})
const resumePreviewUrl = computed(() => {
  if (!generatedResume.value?.html_path) {
    return ''
  }

  const apiBase = new URL(import.meta.env.VITE_API_BASE_URL)
  return `${apiBase.origin}/storage/${generatedResume.value.html_path}`
})
const currentResumeMatch = computed(() => {
  if (!generatedResume.value || !generatedResumeProfileId.value) {
    return null
  }

  if (
    latestMatch.value
    && latestMatch.value.job_id === generatedResume.value.job_id
    && latestMatch.value.profile_id === generatedResumeProfileId.value
  ) {
    return latestMatch.value
  }

  return null
})
const applicationDecisionAction = computed(() => currentResumeMatch.value?.recommendation_action ?? null)
const applicationDecisionMessage = computed(() => {
  switch (applicationDecisionAction.value) {
    case 'skip':
      return 'This match is currently marked as Skip. Creating an application is still allowed, but the role has substantial fit gaps.'
    case 'consider':
      return 'This match is marked as Consider. Review the missing requirements before turning it into a tracked application.'
    default:
      return ''
  }
})

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

async function openDetails(jobId: number): Promise<void> {
  detailsDialogVisible.value = true
  detailsLoading.value = true

  try {
    selectedJob.value = await getJob(jobId)
    upsertJob(selectedJob.value)
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Failed to load job', detail: getApiErrorMessage(error, 'Could not load job details.'), life: 4000 })
    detailsDialogVisible.value = false
  } finally {
    detailsLoading.value = false
  }
}

async function handleAnalyze(jobId: number, force = false): Promise<void> {
  actionLoading.value.analyze = jobId

  try {
    const updatedJob = await analyzeJob(jobId, { force })
    upsertJob(updatedJob)
    if (selectedJob.value?.id === jobId) {
      selectedJob.value = updatedJob
    }

    toast.add({
      severity: 'success',
      summary: force ? 'Job re-analyzed' : 'Job analyzed',
      detail: `Analysis completed for "${updatedJob.title}".`,
      life: 3500,
    })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Analyze failed', detail: getApiErrorMessage(error, 'Failed to analyze job.'), life: 4500 })
  } finally {
    actionLoading.value.analyze = null
  }
}

async function startProfileAction(actionType: Exclude<PendingActionType, null>, job: Job, force = false): Promise<void> {
  pendingActionType.value = actionType
  pendingJobId.value = job.id
  pendingForce.value = force
  profilesLoading.value = true
  profileDialogVisible.value = false
  selectedProfileId.value = null

  try {
    if (profiles.value.length === 0) {
      const collection = await listCandidateProfiles()
      profiles.value = collection.items
    }

    if (profiles.value.length === 0) {
      profileSelectionState.value = 'none'
      profileDialogVisible.value = true
      toast.add({ severity: 'warn', summary: 'Profile required', detail: 'Create a candidate profile before matching or generating a resume.', life: 4000 })
      return
    }

    if (profiles.value.length === 1) {
      selectedProfileId.value = profiles.value[0].id
      await confirmProfileAction()
      return
    }

    profileSelectionState.value = 'ready'
    selectedProfileId.value = profiles.value[0].id
    profileDialogVisible.value = true
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Profiles load failed', detail: getApiErrorMessage(error, 'Failed to load candidate profiles.'), life: 4000 })
  } finally {
    profilesLoading.value = false
  }
}

async function confirmProfileAction(): Promise<void> {
  if (!pendingJobId.value || !selectedProfileId.value || !pendingActionType.value) {
    return
  }

  profileDialogVisible.value = false

  if (pendingActionType.value === 'match') {
    await executeMatch(pendingJobId.value, selectedProfileId.value)
    return
  }

  await executeResume(pendingJobId.value, selectedProfileId.value)
}

async function executeMatch(jobId: number, profileId: number): Promise<void> {
  actionLoading.value.match = jobId

  try {
    const updatedJob = await matchJob(jobId, profileId, { force: pendingForce.value })
    upsertJob(updatedJob)
    latestMatch.value = updatedJob.matches?.[0] ?? null
    if (selectedJob.value?.id === jobId) {
      selectedJob.value = updatedJob
    }

    if (latestMatch.value) {
      matchDialogVisible.value = true
    }

    toast.add({
      severity: 'success',
      summary: pendingForce.value ? 'Match re-run' : 'Job matched',
      detail: `Match completed for "${updatedJob.title}".`,
      life: 3500,
    })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Match failed', detail: getApiErrorMessage(error, 'Failed to match job.'), life: 4500 })
  } finally {
    actionLoading.value.match = null
    resetPendingAction()
  }
}

async function executeResume(jobId: number, profileId: number): Promise<void> {
  actionLoading.value.resume = jobId

  try {
    const resume = await generateResume(jobId, profileId, {
      force: pendingForce.value,
      versionName: generatedResume.value?.job_id === jobId && generatedResumeProfileId.value === profileId
        ? (generatedResume.value.version_name ?? 'v1')
        : undefined,
    })
    generatedResume.value = resume
    generatedResumeProfileId.value = profileId
    resumeDialogVisible.value = true
    toast.add({
      severity: 'success',
      summary: pendingForce.value ? 'Resume regenerated' : 'Resume generated',
      detail: 'Tailored resume draft created successfully.',
      life: 3500,
    })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Resume generation failed', detail: getApiErrorMessage(error, 'Failed to generate resume.'), life: 4500 })
  } finally {
    actionLoading.value.resume = null
    resetPendingAction()
  }
}

function resetPendingAction(): void {
  pendingActionType.value = null
  pendingJobId.value = null
  selectedProfileId.value = null
  pendingForce.value = false
}

function upsertJob(job: Job): void {
  const index = jobs.value.findIndex((item) => item.id === job.id)
  if (index === -1) {
    jobs.value.unshift(job)
    return
  }

  jobs.value.splice(index, 1, job)
}

async function copyRawPayload(): Promise<void> {
  try {
    await copyText(rawPayloadText.value)
    toast.add({ severity: 'success', summary: 'Copied', detail: 'Raw payload JSON copied to clipboard.', life: 2500 })
  } catch {
    toast.add({ severity: 'error', summary: 'Copy failed', detail: 'Could not copy raw payload.', life: 3000 })
  }
}

async function copyResumeContent(): Promise<void> {
  if (!generatedResume.value) {
    return
  }

  const content = [
    generatedResume.value.headline || '',
    '',
    generatedResume.value.professional_summary || '',
    '',
    'Skills:',
    ...(generatedResume.value.selected_skills || []),
    '',
    'Experience:',
    ...(generatedResume.value.selected_experience_bullets || []),
    '',
    'Projects:',
    ...(generatedResume.value.selected_projects || []),
  ].join('\n')

  try {
    await copyText(content)
    toast.add({ severity: 'success', summary: 'Copied', detail: 'Resume content copied to clipboard.', life: 2500 })
  } catch {
    toast.add({ severity: 'error', summary: 'Copy failed', detail: 'Could not copy resume content.', life: 3000 })
  }
}

async function handleDownloadGeneratedResumePdf(): Promise<void> {
  if (!generatedResume.value) {
    return
  }

  downloadingGeneratedResumePdf.value = true

  try {
    await downloadResumePdf(generatedResume.value.id)
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'PDF download failed',
      detail: getApiErrorMessage(error, 'The PDF could not be downloaded.'),
      life: 4500,
    })
  } finally {
    downloadingGeneratedResumePdf.value = false
  }
}

async function handleCreateApplication(): Promise<void> {
  if (!generatedResume.value || !generatedResumeProfileId.value) {
    return
  }

  if (applicationDecisionAction.value === 'consider' || applicationDecisionAction.value === 'skip') {
    applicationGuardDialogVisible.value = true
    return
  }

  await confirmCreateApplication()
}

async function confirmCreateApplication(): Promise<void> {
  if (!generatedResume.value || !generatedResumeProfileId.value) {
    return
  }

  creatingApplication.value = true

  try {
    applicationGuardDialogVisible.value = false
    await createApplication({
      job_id: generatedResume.value.job_id,
      candidate_profile_id: generatedResumeProfileId.value,
      profile_id: generatedResumeProfileId.value,
      tailored_resume_id: generatedResume.value.id,
      resume_id: generatedResume.value.id,
      status: 'ready_to_apply',
      notes: 'Created from generated tailored resume.',
    })

    toast.add({
      severity: 'success',
      summary: 'Application created',
      detail: 'Application was created from the generated resume.',
      life: 3500,
    })

    await router.push('/applications')
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Application creation failed',
      detail: getApiErrorMessage(error, 'Failed to create application from generated resume.'),
      life: 4500,
    })
  } finally {
    creatingApplication.value = false
  }
}

async function goToCandidateProfiles(): Promise<void> {
  profileDialogVisible.value = false
  await router.push('/candidate-profile')
}

async function rerunLatestMatch(): Promise<void> {
  if (!latestMatch.value?.job_id || !latestMatch.value?.profile_id) {
    return
  }

  pendingForce.value = true
  await executeMatch(latestMatch.value.job_id, latestMatch.value.profile_id)
}

async function rerunGeneratedResume(): Promise<void> {
  if (!generatedResume.value || !generatedResumeProfileId.value) {
    return
  }

  pendingActionType.value = 'resume'
  pendingJobId.value = generatedResume.value.job_id
  pendingForce.value = true
  await executeResume(generatedResume.value.job_id, generatedResumeProfileId.value)
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

function formatDuration(value?: number | null): string {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    return 'N/A'
  }

  return `${value} ms`
}

function yesNo(value?: boolean | null): string {
  return value ? 'Yes' : 'No'
}

function formatExperienceRange(analysis?: Job['analysis'] | null): string {
  if (!analysis) {
    return 'N/A'
  }

  const min = analysis.years_experience_min
  const max = analysis.years_experience_max

  if (typeof min === 'number' && typeof max === 'number') {
    return `${min}-${max} years`
  }

  if (typeof min === 'number') {
    return `${min}+ years`
  }

  if (typeof max === 'number') {
    return `Up to ${max} years`
  }

  return 'N/A'
}

function formatCategoryLabel(category: string): string {
  return category.replace(/_/g, ' ')
}

function previewItems(items?: string[] | null, limit = 3): string[] {
  if (!Array.isArray(items)) {
    return []
  }

  return items.slice(0, limit)
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

function recommendationSeverity(recommendation?: string | null): 'success' | 'info' | 'warn' | 'danger' {
  switch (recommendation) {
    case 'strong_match':
      return 'success'
    case 'good_match':
      return 'info'
    case 'weak_match':
      return 'warn'
    default:
      return 'danger'
  }
}

function recommendationActionSeverity(action?: string | null): 'success' | 'info' | 'warn' | 'danger' {
  switch (action) {
    case 'apply':
      return 'success'
    case 'consider':
      return 'warn'
    default:
      return 'danger'
  }
}

function decisionLabel(action?: string | null): string {
  switch (action) {
    case 'apply':
      return 'Apply'
    case 'consider':
      return 'Consider'
    case 'skip':
      return 'Skip'
    default:
      return 'Undecided'
  }
}

function applySummary(match: JobMatch): string {
  switch (match.recommendation_action) {
    case 'apply':
      return 'The current profile is aligned enough to submit an application.'
    case 'consider':
      return 'There is solid overlap, but review missing requirements before applying.'
    case 'skip':
      return 'The role has enough gaps that it should not be prioritized right now.'
    default:
      return 'No clear application recommendation was generated.'
  }
}

function normalizeScore(value?: number | null): number {
  if (typeof value !== 'number' || Number.isNaN(value)) {
    return 0
  }

  if (value <= 1) {
    return Math.round(value * 100)
  }

  return Math.round(value)
}
</script>
