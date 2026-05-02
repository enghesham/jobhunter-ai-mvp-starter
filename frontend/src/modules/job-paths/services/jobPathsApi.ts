import api from '@/app/services/api'
import { extractApiData } from '@/shared/utils/api'
import type { JobPath, JobPathPayload } from '@/modules/job-paths/types'

export async function createJobPath(payload: JobPathPayload): Promise<JobPath> {
  const response = await api.post('/jobhunter/job-paths', payload)
  return extractApiData<JobPath>(response.data)
}
