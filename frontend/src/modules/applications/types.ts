import type { CandidateProfile } from '@/modules/candidate-profile/types'

export type ApplicationStatus =
  | 'draft'
  | 'ready_to_apply'
  | 'applied'
  | 'interviewing'
  | 'rejected'
  | 'offer'
  | 'archived'

export type ApplicationEventType =
  | 'application_created'
  | 'status_changed'
  | 'resume_linked'
  | 'applied_manually'
  | 'interview_scheduled'
  | 'follow_up_scheduled'
  | 'follow_up_sent'
  | 'response_received'
  | 'offer_received'
  | 'rejected'
  | 'archived'
  | 'note_added'

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
  events?: ApplicationEvent[]
}

export interface ApplicationEvent {
  id: number
  type: ApplicationEventType
  note?: string | null
  metadata?: Record<string, unknown>
  occurred_at?: string | null
  created_at?: string | null
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

export interface ApplicationEventPayload {
  type: ApplicationEventType
  note?: string | null
  metadata?: Record<string, unknown> | null
  occurred_at?: string | null
}
