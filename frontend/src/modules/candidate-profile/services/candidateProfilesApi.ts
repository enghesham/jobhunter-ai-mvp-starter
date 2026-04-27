import api from '@/app/services/api'
import type { CollectionResponse } from '@/shared/types'
import { extractApiData, extractCollection } from '@/shared/utils/api'
import type { CandidateProfile, CandidateProfilePayload } from '@/modules/candidate-profile/types'

export async function listProfiles(): Promise<CollectionResponse<CandidateProfile>> {
  const response = await api.get('/jobhunter/candidate-profiles')
  return extractCollection<CandidateProfile>(response.data)
}

export async function getProfile(id: number): Promise<CandidateProfile> {
  const response = await api.get(`/jobhunter/candidate-profiles/${id}`)
  return extractApiData<CandidateProfile>(response.data)
}

export async function createProfile(payload: CandidateProfilePayload): Promise<CandidateProfile> {
  const response = await api.post('/jobhunter/candidate-profiles', payload)
  return extractApiData<CandidateProfile>(response.data)
}

export async function importProfile(payload: CandidateProfilePayload): Promise<CandidateProfile> {
  const response = await api.post('/jobhunter/candidate-profiles/import', payload)
  return extractApiData<CandidateProfile>(response.data)
}

export async function updateProfile(id: number, payload: CandidateProfilePayload): Promise<CandidateProfile> {
  const response = await api.patch(`/jobhunter/candidate-profiles/${id}`, payload)
  return extractApiData<CandidateProfile>(response.data)
}

export async function deleteProfile(id: number): Promise<void> {
  await api.delete(`/jobhunter/candidate-profiles/${id}`)
}
