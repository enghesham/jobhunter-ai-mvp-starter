import type { JobSource } from '@/modules/job-sources/types'

export interface JobAnalysis {
  required_skills?: string[]
  preferred_skills?: string[]
  seniority?: string | null
  role_type?: string | null
  domain_tags?: string[]
  ai_summary?: string | null
}

export interface JobMatch {
  id: number
  overall_score?: number | null
  recommendation?: string | null
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
