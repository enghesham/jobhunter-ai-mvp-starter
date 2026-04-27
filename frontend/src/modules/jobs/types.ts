import type { JobSource } from '@/modules/job-sources/types'
import type { CandidateProfile } from '@/modules/candidate-profile/types'

export interface JobAnalysis {
  required_skills?: string[]
  preferred_skills?: string[]
  seniority?: string | null
  role_type?: string | null
  domain_tags?: string[]
  ai_summary?: string | null
  analyzed_at?: string | null
}

export interface JobMatch {
  id: number
  job_id?: number
  profile_id?: number
  candidate_profile_id?: number
  overall_score?: number | null
  title_score?: number | null
  skill_score?: number | null
  seniority_score?: number | null
  location_score?: number | null
  backend_focus_score?: number | null
  domain_score?: number | null
  recommendation?: string | null
  notes?: string | null
  job?: {
    id?: number
    title?: string | null
    company_name?: string | null
    url?: string | null
  } | null
  candidate_profile?: {
    id?: number
    full_name?: string | null
    headline?: string | null
  } | null
  matched_at?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface TailoredResume {
  id: number
  job_id: number
  profile_id: number
  candidate_profile_id?: number
  version_name?: string | null
  headline?: string | null
  professional_summary?: string | null
  selected_skills?: string[]
  selected_experience_bullets?: string[]
  selected_projects?: string[]
  ats_keywords?: string[]
  html_path?: string | null
  pdf_path?: string | null
  html_url?: string | null
  pdf_url?: string | null
  job?: {
    id?: number
    title?: string | null
    company_name?: string | null
    url?: string | null
  } | null
  candidate_profile?: {
    id?: number
    full_name?: string | null
    headline?: string | null
  } | null
  created_at?: string | null
  updated_at?: string | null
}

export interface Job {
  id: number
  external_id?: string | null
  company_name?: string | null
  title: string
  location?: string | null
  is_remote: boolean
  remote_type?: string | null
  employment_type?: string | null
  description_clean?: string | null
  description_raw?: string | null
  url?: string | null
  raw_payload?: Record<string, unknown> | null
  salary_text?: string | null
  posted_at?: string | null
  status?: string | null
  source?: JobSource | null
  analysis?: JobAnalysis | null
  matches?: JobMatch[]
  created_at?: string | null
  updated_at?: string | null
}
