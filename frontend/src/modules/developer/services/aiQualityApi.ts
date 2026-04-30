import api from '@/app/services/api'
import type { ApiSuccessResponse } from '@/shared/types'
import { extractApiData } from '@/shared/utils/api'
import type { AiQualityReport } from '@/modules/developer/types'

export async function getAiQualityReport(): Promise<AiQualityReport> {
  const response = await api.get<ApiSuccessResponse<AiQualityReport> | AiQualityReport>('/jobhunter/ai-quality')

  return extractApiData<AiQualityReport>(response.data)
}
