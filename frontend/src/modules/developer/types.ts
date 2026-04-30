export interface AiQualitySummary {
  total_runs: number
  ai_success_runs: number
  fallback_runs: number
  ai_success_rate: number | null
  fallback_rate: number | null
  average_duration_ms: number | null
  average_confidence_score: number | null
  provider_error_count: number
  cache_hit_rate: number | null
}

export interface AiQualityProviderMetric {
  provider: string
  provider_label: string
  total_runs: number
  ai_success_runs: number
  fallback_runs: number
  ai_success_rate: number | null
  fallback_rate: number | null
  average_duration_ms: number | null
  average_confidence_score: number | null
  provider_error_count: number
  cache_hit_rate: number | null
  top_operation: string | null
}

export interface AiQualityOperationMetric {
  operation: string
  operation_label: string
  total_runs: number
  ai_success_runs: number
  fallback_runs: number
  ai_success_rate: number | null
  fallback_rate: number | null
  average_duration_ms: number | null
  cache_hit_rate: number | null
}

export interface AiQualityCacheStats {
  observed_hits: number
  observed_misses: number
  observed_events: number
  hit_rate: number | null
  cacheable_records: number
  unique_input_hashes: number
  duplicate_input_hash_groups: number
  duplicate_input_hash_records: number
  source: string
  note: string
}

export interface AiQualityProviderError {
  provider: string
  provider_label: string
  operation: string
  operation_label: string
  message: string
  count: number
}

export interface AiQualityRun {
  operation: string
  operation_label: string
  resource_type: string
  resource_id: number
  label: string
  provider: string
  provider_label: string
  model: string | null
  prompt_version: string | null
  input_hash: string | null
  ai_duration_ms: number | null
  fallback_used: boolean
  ai_success: boolean
  ai_confidence_score: number | null
  ai_generated_at: string | null
  created_at: string | null
}

export interface AiQualityReport {
  summary: AiQualitySummary
  providers: AiQualityProviderMetric[]
  operations: AiQualityOperationMetric[]
  cache: AiQualityCacheStats
  top_errors: AiQualityProviderError[]
  recent_runs: AiQualityRun[]
  generated_at: string
}
