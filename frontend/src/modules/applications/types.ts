import type { CandidateProfile } from '@/modules/candidate-profile/types'

export type ApplicationStatus = 'draft' | 'ready_to_apply' | 'applied' | 'rejected' | 'interview' | 'offer'

export interface ApplicationJob {
  id: number
  title?: string | null
  company_name?: string | null
  url?: string | null
}

export interface ApplicationResume {
  id: number
  headline?: string | null
  html_path?: string | null
  pdf_path?: string | null
}

export interface Application {
  id: number
  job_id: number
  profile_id?: number
  candidate_profile_id?: number
  tailored_resume_id?: number | null
  resume_id?: number | null
  status: ApplicationStatus
  notes?: string | null
  applied_at?: string | null
  follow_up_date?: string | null
  company_response?: string | null
  interview_date?: string | null
  created_at?: string | null
  updated_at?: string | null
  job?: ApplicationJob | null
  candidate_profile?: CandidateProfile | { id: number; full_name?: string | null; headline?: string | null } | null
  resume?: ApplicationResume | null
}

export interface ApplicationPayload {
  job_id: number
  profile_id?: number
  candidate_profile_id?: number
  tailored_resume_id?: number | null
  resume_id?: number | null
  status: ApplicationStatus
  notes?: string | null
}
