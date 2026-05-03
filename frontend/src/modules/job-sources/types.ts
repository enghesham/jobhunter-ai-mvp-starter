export type JobSourceType = 'custom' | 'rss' | 'greenhouse' | 'lever' | (string & {})

export interface JobSource {
  id: number
  name: string
  type: JobSourceType
  url: string
  company_name?: string | null
  is_active: boolean
  config?: Record<string, unknown> | null
  jobs_count?: number | null
  created_at?: string | null
  updated_at?: string | null
}

export interface JobSourcePayload {
  name: string
  type: string
  url: string
  is_active: boolean
  config?: Record<string, unknown> | null
}

export interface IngestionJobInput {
  external_id?: string | null
  title: string
  company: string
  location?: string | null
  is_remote: boolean
  url: string
  description: string
  raw_payload?: Record<string, unknown> | null
  status?: string
}

export interface IngestionResult {
  source_id: number
  created: number
  updated: number
  skipped: number
  jobs: unknown[]
}
