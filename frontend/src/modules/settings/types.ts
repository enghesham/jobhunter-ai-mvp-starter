export interface AnswerTemplate {
  id: number
  key: string
  title: string
  base_answer: string
  tags?: string[] | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AnswerTemplatePayload {
  key: string
  title: string
  base_answer: string
  tags?: string[] | null
}
