import api from '@/app/services/api'
import type { CollectionResponse } from '@/shared/types'
import { extractCollection } from '@/shared/utils/api'
import type { Job } from '@/modules/jobs/types'

export async function listJobs(): Promise<CollectionResponse<Job>> {
  const response = await api.get('/jobhunter/jobs')
  return extractCollection<Job>(response.data)
}
