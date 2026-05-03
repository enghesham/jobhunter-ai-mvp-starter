import api from '@/app/services/api'
import type { CollectionResponse } from '@/shared/types'
import { extractCollection } from '@/shared/utils/api'
import type { JobMatch } from '@/modules/jobs/types'

export interface MatchFilters {
  bestOnly?: boolean
}

export async function listMatches(filters: MatchFilters = {}): Promise<CollectionResponse<JobMatch>> {
  const response = await api.get('/jobhunter/matches', {
    params: {
      best_only: filters.bestOnly ? 1 : undefined,
    },
  })

  return extractCollection<JobMatch>(response.data)
}
