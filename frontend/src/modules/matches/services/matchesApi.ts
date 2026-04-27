import api from '@/app/services/api'
import type { CollectionResponse } from '@/shared/types'
import { extractCollection } from '@/shared/utils/api'
import type { JobMatch } from '@/modules/jobs/types'

export async function listMatches(): Promise<CollectionResponse<JobMatch>> {
  const response = await api.get('/jobhunter/matches')
  return extractCollection<JobMatch>(response.data)
}
