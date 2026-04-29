import api from '@/app/services/api'
import type { CollectionResponse } from '@/shared/types'
import { extractApiData, extractCollection } from '@/shared/utils/api'
import type { CandidateProfile, Job, TailoredResume } from '@/modules/jobs/types'

interface ForceOption {
  force?: boolean
}

export async function listJobs(): Promise<CollectionResponse<Job>> {
  const response = await api.get('/jobhunter/jobs')
  return extractCollection<Job>(response.data)
}

export async function getJob(id: number): Promise<Job> {
  const response = await api.get(`/jobhunter/jobs/${id}`)
  return extractApiData<Job>(response.data)
}

export async function analyzeJob(id: number, options: ForceOption = {}): Promise<Job> {
  const response = await api.post(`/jobhunter/jobs/${id}/analyze`, {
    force: options.force ?? false,
  })
  return extractApiData<Job>(response.data)
}

export async function matchJob(id: number, candidateProfileId: number, options: ForceOption = {}): Promise<Job> {
  const response = await api.post(`/jobhunter/jobs/${id}/match`, {
    candidate_profile_id: candidateProfileId,
    profile_id: candidateProfileId,
    force: options.force ?? false,
  })

  return extractApiData<Job>(response.data)
}

export async function generateResume(
  id: number,
  candidateProfileId: number,
  options: ForceOption & { versionName?: string | null } = {},
): Promise<TailoredResume> {
  const response = await api.post(`/jobhunter/jobs/${id}/generate-resume`, {
    candidate_profile_id: candidateProfileId,
    profile_id: candidateProfileId,
    version_name: options.versionName ?? undefined,
    force: options.force ?? false,
  })

  return extractApiData<TailoredResume>(response.data)
}

export async function listCandidateProfiles(): Promise<CollectionResponse<CandidateProfile>> {
  const response = await api.get('/jobhunter/candidate-profiles')
  return extractCollection<CandidateProfile>(response.data)
}
