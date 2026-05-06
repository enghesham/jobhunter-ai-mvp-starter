import type { Job, JobMatch } from '@/modules/jobs/types'
import type { JobPath } from '@/modules/job-paths/types'

export interface JobOpportunity {
  id: number
  job_id: number
  job_path_id?: number | null
  career_profile_id?: number | null
  match_id?: number | null
  context_key: string
  quick_relevance_score: number
  match_score?: number | null
  display_score: number
  is_evaluated?: boolean
  status: string
  recommendation?: string | null
  reasons: string[]
  matched_keywords: string[]
  missing_keywords: string[]
  thresholds?: {
    min_relevance_score: number
    min_match_score: number
    quick_recommended_score: number
  } | null
  hidden_at?: string | null
  hidden_reason?: string | null
  evaluated_at?: string | null
  apply_package_id?: number | null
  apply_package?: {
    id: number
    status?: string | null
    application_id?: number | null
    resume_id?: number | null
    created_at?: string | null
    updated_at?: string | null
  } | null
  job?: Job | null
  job_path?: Pick<JobPath, 'id' | 'name' | 'min_relevance_score' | 'min_match_score'> | null
  career_profile?: {
    id?: number
    full_name?: string | null
    headline?: string | null
  } | null
  match?: JobMatch | null
  created_at?: string | null
  updated_at?: string | null
}

export interface OpportunityRefreshResponse {
  stats: {
    created: number
    updated: number
    skipped: number
    evaluated: number
  }
  opportunities: JobOpportunity[]
}

export interface JobCollectionRun {
  id: number
  job_path_id?: number | null
  status: string
  source_count: number
  fetched_count: number
  accepted_count: number
  created_count: number
  updated_count: number
  duplicate_count: number
  filtered_count: number
  failed_count: number
  opportunities_created: number
  opportunities_updated: number
  error_message?: string | null
  started_at?: string | null
  finished_at?: string | null
}

export interface JobCollectionResponse {
  queued: number
  processed: number
  runs?: JobCollectionRun[]
}
