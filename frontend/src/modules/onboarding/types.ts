export interface OnboardingState {
  current_step: string
  is_completed: boolean
  completed_at?: string | null
  metadata: Record<string, unknown>
  next_action?: string
}

export interface CareerUnderstanding {
  role: string
  seniority: string
  skills: string[]
  experience: {
    years: number
    headline?: string | null
    summary?: string | null
  }
}

export interface OnboardingCareerProfile {
  id: number
  display_name: string
  title: string
  professional_summary: string
  primary_role?: string | null
  seniority_level?: string | null
  years_of_experience: number
  skills: string[]
  secondary_skills: string[]
  industries?: string[]
  preferred_workplace_type?: string | null
  preferred_locations: string[]
  preferred_job_types?: string[]
  raw_cv_text?: string | null
  experiences?: OnboardingExperience[]
}

export interface OnboardingExperience {
  id?: number
  company: string
  title: string
  start_date?: string | null
  end_date?: string | null
  description: string
  achievements?: string[]
  skills?: string[]
}

export interface OnboardingCareerProfilePayload {
  display_name: string
  title: string
  professional_summary: string
  primary_role?: string | null
  seniority_level?: string | null
  years_of_experience: number
  skills: string[]
  secondary_skills: string[]
  industries: string[]
  preferred_workplace_type?: string | null
  preferred_locations: string[]
  preferred_job_types: string[]
  raw_cv_text?: string | null
  experiences?: OnboardingExperience[]
  source?: 'manual' | 'cv_upload' | 'ai_generated'
}

export interface SuggestedJobPath {
  career_profile_id?: number | null
  name: string
  description?: string | null
  target_roles: string[]
  target_domains: string[]
  include_keywords: string[]
  exclude_keywords: string[]
  required_skills: string[]
  optional_skills: string[]
  seniority_levels: string[]
  preferred_locations: string[]
  preferred_countries: string[]
  preferred_job_types: string[]
  remote_preference?: 'remote' | 'hybrid' | 'onsite' | 'any' | null
  min_relevance_score: number
  min_match_score: number
  salary_min?: number | null
  salary_currency?: string | null
  is_active: boolean
  auto_collect_enabled: boolean
  notifications_enabled: boolean
  metadata?: Record<string, unknown>
}

export interface OnboardingPayload {
  state: OnboardingState
  career_profile: OnboardingCareerProfile | null
  understanding: CareerUnderstanding | null
  best_matches_path?: string
}

export interface CareerProfileSaveResponse {
  state: OnboardingState
  career_profile: OnboardingCareerProfile
  understanding: CareerUnderstanding
}

export interface JobPathSuggestionsResponse {
  state: OnboardingState
  career_profile: OnboardingCareerProfile
  suggestions: SuggestedJobPath[]
}
