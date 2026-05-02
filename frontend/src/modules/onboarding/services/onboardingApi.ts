import api from '@/app/services/api'
import { extractApiData } from '@/shared/utils/api'
import type {
  CareerProfileSaveResponse,
  JobPathSuggestionsResponse,
  OnboardingCareerProfilePayload,
  OnboardingPayload,
} from '@/modules/onboarding/types'

export async function getOnboarding(): Promise<OnboardingPayload> {
  const response = await api.get('/jobhunter/onboarding')
  return extractApiData<OnboardingPayload>(response.data)
}

export async function saveOnboardingCareerProfile(payload: OnboardingCareerProfilePayload): Promise<CareerProfileSaveResponse> {
  const response = await api.post('/jobhunter/onboarding/career-profile', payload)
  return extractApiData<CareerProfileSaveResponse>(response.data)
}

export async function suggestOnboardingJobPaths(careerProfileId?: number | null): Promise<JobPathSuggestionsResponse> {
  const response = await api.post('/jobhunter/onboarding/suggest-job-paths', {
    career_profile_id: careerProfileId ?? undefined,
  })
  return extractApiData<JobPathSuggestionsResponse>(response.data)
}

export async function completeOnboarding(): Promise<Pick<OnboardingPayload, 'state' | 'best_matches_path'>> {
  const response = await api.post('/jobhunter/onboarding/complete')
  return extractApiData<Pick<OnboardingPayload, 'state' | 'best_matches_path'>>(response.data)
}
