import api from '@/app/services/api'
import { extractApiData } from '@/shared/utils/api'
import type { OpportunityPreferences, OpportunityPreferencesPayload } from '@/modules/settings/types'

export async function getOpportunityPreferences(): Promise<OpportunityPreferences> {
  const response = await api.get('/jobhunter/opportunity-preferences')

  return extractApiData<OpportunityPreferences>(response.data)
}

export async function updateOpportunityPreferences(payload: OpportunityPreferencesPayload): Promise<OpportunityPreferences> {
  const response = await api.patch('/jobhunter/opportunity-preferences', payload)

  return extractApiData<OpportunityPreferences>(response.data)
}
