import type { SuggestedJobPath } from '@/modules/onboarding/types'

export type JobPathPayload = SuggestedJobPath

export interface JobPath extends SuggestedJobPath {
  id: number
  created_at?: string | null
  updated_at?: string | null
}
