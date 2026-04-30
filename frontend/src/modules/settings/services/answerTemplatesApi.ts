import api from '@/app/services/api'
import type { CollectionResponse } from '@/shared/types'
import { extractApiData, extractCollection } from '@/shared/utils/api'
import type { AnswerTemplate, AnswerTemplatePayload } from '@/modules/settings/types'

export async function listAnswerTemplates(): Promise<CollectionResponse<AnswerTemplate>> {
  const response = await api.get('/jobhunter/answer-templates')
  return extractCollection<AnswerTemplate>(response.data)
}

export async function createAnswerTemplate(payload: AnswerTemplatePayload): Promise<AnswerTemplate> {
  const response = await api.post('/jobhunter/answer-templates', payload)
  return extractApiData<AnswerTemplate>(response.data)
}

export async function updateAnswerTemplate(id: number, payload: Partial<AnswerTemplatePayload>): Promise<AnswerTemplate> {
  const response = await api.patch(`/jobhunter/answer-templates/${id}`, payload)
  return extractApiData<AnswerTemplate>(response.data)
}

export async function deleteAnswerTemplate(id: number): Promise<void> {
  await api.delete(`/jobhunter/answer-templates/${id}`)
}

export async function bootstrapDefaultAnswerTemplates(): Promise<CollectionResponse<AnswerTemplate>> {
  const response = await api.post('/jobhunter/answer-templates/bootstrap-defaults')
  return extractCollection<AnswerTemplate>(response.data)
}
