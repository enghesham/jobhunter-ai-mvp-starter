import type { AxiosError } from 'axios'

import type { ApiErrorResponse, ApiSuccessResponse, CollectionResponse, PaginatedPayload, PaginationMeta } from '@/shared/types'

type PaginatedLike<T> = PaginatedPayload<T> | { data: T[] } | T[]

export function extractApiData<T>(payload: ApiSuccessResponse<T> | T): T {
  if (isApiSuccessResponse<T>(payload)) {
    return payload.data
  }

  return payload
}

export function extractCollection<T>(payload: ApiSuccessResponse<PaginatedLike<T>> | PaginatedLike<T>): CollectionResponse<T> {
  const resolved = extractApiData(payload)

  if (Array.isArray(resolved)) {
    return {
      items: resolved,
      meta: null,
    }
  }

  if (isPaginatedPayload<T>(resolved)) {
    return {
      items: resolved.data,
      meta: {
        current_page: resolved.current_page,
        from: resolved.from,
        last_page: resolved.last_page,
        path: resolved.path,
        per_page: resolved.per_page,
        to: resolved.to,
        total: resolved.total,
        links: resolved.links,
      },
    }
  }

  if (Array.isArray(resolved.data)) {
    return {
      items: resolved.data,
      meta: null,
    }
  }

  return {
    items: [],
    meta: null,
  }
}

export function getCollectionTotal<T>(collection: CollectionResponse<T>): number {
  return collection.meta?.total ?? collection.items.length
}

export function getApiErrorMessage(error: unknown, fallback = 'Request failed.'): string {
  const axiosError = error as AxiosError<ApiErrorResponse>
  const payload = axiosError.response?.data

  if (payload?.message) {
    return payload.message
  }

  if (axiosError.response?.status === 403) {
    return 'You do not have permission to perform this action.'
  }

  if (axiosError.response && axiosError.response.status >= 500) {
    return 'The server encountered an error. Please retry shortly.'
  }

  if (axiosError.code === 'ECONNABORTED' || !axiosError.response) {
    return 'The request could not reach the server. Check your connection and retry.'
  }

  return fallback
}

export function getApiValidationErrors(error: unknown): Record<string, string[]> {
  const axiosError = error as AxiosError<ApiErrorResponse>
  return axiosError.response?.data?.errors ?? {}
}

function isApiSuccessResponse<T>(payload: ApiSuccessResponse<T> | T): payload is ApiSuccessResponse<T> {
  return typeof payload === 'object' && payload !== null && 'success' in payload && 'data' in payload
}

function isPaginatedPayload<T>(payload: PaginatedLike<T>): payload is PaginatedPayload<T> {
  return typeof payload === 'object' && payload !== null && !Array.isArray(payload) && 'data' in payload && Array.isArray(payload.data)
}
