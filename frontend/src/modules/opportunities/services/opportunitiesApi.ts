import api from '@/app/services/api'
import { extractApiData, extractCollection } from '@/shared/utils/api'
import type { CollectionResponse } from '@/shared/types'
import type {
  JobCollectionResponse,
  JobOpportunity,
  OpportunityProfileSkillsResponse,
  OpportunityRefreshResponse,
} from '@/modules/opportunities/types'

export interface OpportunityFilters {
  jobPathId?: number | null
  includeHidden?: boolean
  showDuplicates?: boolean
}

export async function listOpportunities(filters: OpportunityFilters = {}): Promise<CollectionResponse<JobOpportunity>> {
  const response = await api.get('/jobhunter/opportunities', {
    params: {
      job_path_id: filters.jobPathId ?? undefined,
      include_hidden: filters.includeHidden ? 1 : undefined,
      show_duplicates: filters.showDuplicates ? 1 : undefined,
    },
  })

  return extractCollection<JobOpportunity>(response.data)
}

export async function refreshOpportunities(jobPathId?: number | null): Promise<OpportunityRefreshResponse> {
  const response = await api.post('/jobhunter/opportunities/refresh', {
    job_path_id: jobPathId ?? undefined,
  })

  return extractApiData<OpportunityRefreshResponse>(response.data)
}

export async function collectJobsForActivePaths(sync = true): Promise<JobCollectionResponse> {
  const response = await api.post('/jobhunter/job-collection/collect-due', {
    sync,
    all_active: true,
  })

  return extractApiData<JobCollectionResponse>(response.data)
}

export async function evaluateOpportunity(id: number, force = false): Promise<JobOpportunity> {
  const response = await api.post(`/jobhunter/opportunities/${id}/evaluate`, { force })
  return extractApiData<JobOpportunity>(response.data)
}

export async function addOpportunityProfileSkills(id: number, skills: string[]): Promise<OpportunityProfileSkillsResponse> {
  const response = await api.post(`/jobhunter/opportunities/${id}/profile-skills`, { skills })
  return extractApiData<OpportunityProfileSkillsResponse>(response.data)
}

export async function hideOpportunity(id: number, reason?: string | null): Promise<JobOpportunity> {
  const response = await api.post(`/jobhunter/opportunities/${id}/hide`, { reason: reason ?? undefined })
  return extractApiData<JobOpportunity>(response.data)
}

export async function restoreOpportunity(id: number): Promise<JobOpportunity> {
  const response = await api.post(`/jobhunter/opportunities/${id}/restore`)
  return extractApiData<JobOpportunity>(response.data)
}
