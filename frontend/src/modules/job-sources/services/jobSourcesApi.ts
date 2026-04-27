import api from '@/app/services/api'
import type { CollectionResponse } from '@/shared/types'
import { extractApiData, extractCollection } from '@/shared/utils/api'
import type { IngestionJobInput, IngestionResult, JobSource, JobSourcePayload } from '@/modules/job-sources/types'

export async function listJobSources(): Promise<CollectionResponse<JobSource>> {
  const response = await api.get('/jobhunter/job-sources')
  return extractCollection<JobSource>(response.data)
}

export async function getJobSource(id: number): Promise<JobSource> {
  const response = await api.get(`/jobhunter/job-sources/${id}`)
  return extractApiData<JobSource>(response.data)
}

export async function createJobSource(payload: JobSourcePayload): Promise<JobSource> {
  const response = await api.post('/jobhunter/job-sources', payload)
  return extractApiData<JobSource>(response.data)
}

export async function updateJobSource(id: number, payload: JobSourcePayload): Promise<JobSource> {
  const response = await api.patch(`/jobhunter/job-sources/${id}`, payload)
  return extractApiData<JobSource>(response.data)
}

export async function deleteJobSource(id: number): Promise<void> {
  await api.delete(`/jobhunter/job-sources/${id}`)
}

export async function ingestJobs(id: number, jobs: IngestionJobInput[]): Promise<IngestionResult> {
  const response = await api.post(`/jobhunter/job-sources/${id}/ingest`, { jobs })
  return extractApiData<IngestionResult>(response.data)
}
