import type { Application } from '@/modules/applications/types'
import type { Job, TailoredResume } from '@/modules/jobs/types'
import type { JobPath } from '@/modules/job-paths/types'

export type ApplyPackageStatus = 'draft' | 'ready' | 'used' | 'archived'

export interface ApplyPackageAnswer {
  key?: string | null
  title?: string | null
  question?: string | null
  answer?: string | null
  content?: string | null
}

export interface ApplyPackage {
  id: number
  job_id: number
  career_profile_id?: number | null
  candidate_profile_id?: number | null
  job_path_id?: number | null
  application_id?: number | null
  resume_id?: number | null
  cover_letter?: string | null
  application_answers?: ApplyPackageAnswer[] | Record<string, unknown> | null
  salary_answer?: string | null
  notice_period_answer?: string | null
  interest_answer?: string | null
  strengths?: string[]
  gaps?: string[]
  interview_questions?: string[]
  follow_up_email?: string | null
  ai_provider?: string | null
  ai_model?: string | null
  ai_generated_at?: string | null
  ai_confidence_score?: number | null
  ai_duration_ms?: number | null
  prompt_version?: string | null
  fallback_used?: boolean
  status: ApplyPackageStatus
  metadata?: Record<string, unknown>
  job?: Job | null
  job_path?: JobPath | null
  career_profile?: {
    id?: number
    full_name?: string | null
    headline?: string | null
  } | null
  resume?: TailoredResume | null
  application?: Application | null
  created_at?: string | null
  updated_at?: string | null
}

export interface GenerateApplyPackagePayload {
  career_profile_id?: number | null
  profile_id?: number | null
  job_path_id?: number | null
  force?: boolean
}
