import api from '@/app/services/api'
import type { CollectionResponse } from '@/shared/types'
import { extractCollection } from '@/shared/utils/api'
import type { TailoredResume } from '@/modules/jobs/types'

export async function listResumes(): Promise<CollectionResponse<TailoredResume>> {
  const response = await api.get('/jobhunter/resumes')
  return extractCollection<TailoredResume>(response.data)
}
