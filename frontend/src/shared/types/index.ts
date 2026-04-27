export interface ApiSuccessResponse<T> {
  success: true
  data: T
}

export interface ApiErrorResponse {
  success: false
  message: string
  errors?: Record<string, string[]>
}

export interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

export interface PaginationMeta {
  current_page?: number
  from?: number | null
  last_page?: number
  path?: string
  per_page?: number
  to?: number | null
  total?: number
  links?: PaginationLink[]
}

export interface PaginatedPayload<T> {
  data: T[]
  current_page?: number
  from?: number | null
  last_page?: number
  path?: string
  per_page?: number
  to?: number | null
  total?: number
  links?: PaginationLink[]
}

export interface CollectionResponse<T> {
  items: T[]
  meta: PaginationMeta | null
}
