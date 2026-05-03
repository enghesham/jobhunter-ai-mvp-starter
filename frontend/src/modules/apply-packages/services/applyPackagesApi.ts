import api from '@/app/services/api'
import type { Application } from '@/modules/applications/types'
import type { ApplyPackage, GenerateApplyPackagePayload } from '@/modules/apply-packages/types'
import type { CollectionResponse } from '@/shared/types'
import { extractApiData, extractCollection } from '@/shared/utils/api'

export async function listApplyPackages(): Promise<CollectionResponse<ApplyPackage>> {
  const response = await api.get('/jobhunter/apply-packages')
  return extractCollection<ApplyPackage>(response.data)
}

export async function generateApplyPackage(jobId: number, payload: GenerateApplyPackagePayload = {}): Promise<ApplyPackage> {
  const profileId = payload.profile_id ?? payload.career_profile_id ?? null
  const response = await api.post(`/jobhunter/jobs/${jobId}/apply-package`, {
    career_profile_id: profileId,
    profile_id: profileId,
    job_path_id: payload.job_path_id ?? null,
    force: payload.force ?? false,
  })

  return extractApiData<ApplyPackage>(response.data)
}

export async function updateApplyPackage(id: number, payload: Partial<ApplyPackage>): Promise<ApplyPackage> {
  const response = await api.patch(`/jobhunter/apply-packages/${id}`, payload)
  return extractApiData<ApplyPackage>(response.data)
}

export async function createApplicationFromApplyPackage(id: number): Promise<Application> {
  const response = await api.post(`/jobhunter/apply-packages/${id}/create-application`)
  return extractApiData<Application>(response.data)
}
