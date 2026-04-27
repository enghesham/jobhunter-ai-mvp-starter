export interface CandidateExperience {
  id?: number
  company: string
  title: string
  start_date?: string | null
  end_date?: string | null
  description: string
}

export interface CandidateProject {
  id?: number
  name: string
  description: string
  skills: string[]
}

export interface CandidateProfile {
  id: number
  full_name: string
  headline: string
  base_summary: string
  years_experience: number
  preferred_roles: string[]
  preferred_locations: string[]
  preferred_job_types: string[]
  core_skills: string[]
  nice_to_have_skills: string[]
  experiences: CandidateExperience[]
  projects: CandidateProject[]
  linkedin_url?: string | null
  github_url?: string | null
  portfolio_url?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface CandidateProfilePayload {
  full_name: string
  headline: string
  base_summary: string
  years_experience: number
  preferred_roles: string[]
  preferred_locations: string[]
  preferred_job_types: string[]
  core_skills: string[]
  nice_to_have_skills: string[]
  experiences: CandidateExperience[]
  projects: CandidateProject[]
}
