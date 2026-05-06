export interface AnswerTemplate {
  id: number
  key: string
  title: string
  base_answer: string
  tags?: string[] | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AnswerTemplatePayload {
  key: string
  title: string
  base_answer: string
  tags?: string[] | null
}

export interface OpportunityPreferenceValues {
  default_min_relevance_score: number | null
  default_min_match_score: number | null
  quick_recommended_score: number | null
  store_below_threshold: boolean | null
  show_below_threshold: boolean | null
}

export interface EffectiveOpportunityPreferences {
  default_min_relevance_score: number
  default_min_match_score: number
  quick_recommended_score: number
  store_below_threshold: boolean
  show_below_threshold: boolean
}

export interface OpportunityPreferences {
  id: number | null
  values: OpportunityPreferenceValues
  effective: EffectiveOpportunityPreferences
  defaults: EffectiveOpportunityPreferences
  descriptions: Record<keyof OpportunityPreferenceValues, string>
}

export interface OpportunityPreferencesPayload {
  default_min_relevance_score?: number | null
  default_min_match_score?: number | null
  quick_recommended_score?: number | null
  store_below_threshold?: boolean | null
  show_below_threshold?: boolean | null
  apply_to_existing_job_paths?: boolean
}
