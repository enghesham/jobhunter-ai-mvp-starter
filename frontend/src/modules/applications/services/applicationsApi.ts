import api from '@/app/services/api'
import type { CollectionResponse } from '@/shared/types'
import { extractApiData, extractCollection } from '@/shared/utils/api'
import type { Application, ApplicationEvent, ApplicationEventPayload, ApplicationMaterial, ApplicationPayload } from '@/modules/applications/types'

export async function listApplications(): Promise<CollectionResponse<Application>> {
  const response = await api.get('/jobhunter/applications')
  return extractCollection<Application>(response.data)
}

export async function getApplication(id: number): Promise<Application> {
  const response = await api.get(`/jobhunter/applications/${id}`)
  return extractApiData<Application>(response.data)
}

export async function createApplication(payload: ApplicationPayload): Promise<Application> {
  const response = await api.post('/jobhunter/applications', normalizePayload(payload))
  return extractApiData<Application>(response.data)
}

export async function updateApplication(id: number, payload: Partial<ApplicationPayload>): Promise<Application> {
  const response = await api.patch(`/jobhunter/applications/${id}`, normalizePayload(payload))
  return extractApiData<Application>(response.data)
}

export async function deleteApplication(id: number): Promise<void> {
  await api.delete(`/jobhunter/applications/${id}`)
}

export async function createApplicationEvent(id: number, payload: ApplicationEventPayload): Promise<ApplicationEvent> {
  const response = await api.post(`/jobhunter/applications/${id}/events`, {
    type: payload.type,
    note: payload.note ?? null,
    metadata: payload.metadata ?? null,
    occurred_at: payload.occurred_at ?? null,
  })

  return extractApiData<ApplicationEvent>(response.data)
}

export async function listApplicationMaterials(id: number): Promise<ApplicationMaterial[]> {
  const response = await api.get(`/jobhunter/applications/${id}/materials`)
  return extractApiData<ApplicationMaterial[]>(response.data)
}

export async function generateApplicationMaterials(id: number, force = false, sections: string[] = []): Promise<ApplicationMaterial[]> {
  const response = await api.post(`/jobhunter/applications/${id}/generate-materials`, {
    force,
    sections: sections.length > 0 ? sections : undefined,
  })

  return extractApiData<ApplicationMaterial[]>(response.data)
}

function normalizePayload(payload: Partial<ApplicationPayload>): Record<string, unknown> {
  const profileId = payload.profile_id ?? payload.candidate_profile_id
  const resumeId = payload.tailored_resume_id ?? payload.resume_id ?? null

  return {
    job_id: payload.job_id,
    profile_id: profileId,
    candidate_profile_id: profileId,
    job_path_id: payload.job_path_id ?? null,
    apply_package_id: payload.apply_package_id ?? null,
    tailored_resume_id: resumeId,
    resume_id: resumeId,
    status: payload.status,
    notes: payload.notes ?? null,
  }
}
