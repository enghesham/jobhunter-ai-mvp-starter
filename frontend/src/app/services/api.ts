import axios, { type AxiosInstance } from 'axios'

import router from '@/app/router'
import { storageKeys } from '@/shared/utils/storage'

const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  timeout: 15000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(storageKeys.authToken)

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem(storageKeys.authToken)
      window.dispatchEvent(new CustomEvent('jobhunter:unauthorized'))
      window.dispatchEvent(new CustomEvent('jobhunter:toast', {
        detail: {
          severity: 'warn',
          summary: 'Session expired',
          detail: 'Please log in again to continue.',
          life: 4000,
        },
      }))

      if (router.currentRoute.value.path !== '/login') {
        await router.push({ path: '/login', query: { redirect: router.currentRoute.value.fullPath } })
      }
    } else if (error.response?.status === 403) {
      emitHttpToast('Permission denied', error.response?.data?.message ?? 'You do not have access to perform this action.')
    } else if (error.response?.status === 404) {
      emitHttpToast('Not found', 'The requested resource could not be found or may no longer exist.')
    } else if (error.response?.status === 422) {
      emitHttpToast('Validation failed', normalizeValidationMessage(error.response?.data) ?? 'Please review the highlighted fields and try again.')
    } else if (error.response?.status >= 500) {
      emitHttpToast('Server error', 'The server could not complete the request. Please retry in a moment.')
    } else if (error.code === 'ECONNABORTED' || !error.response) {
      emitHttpToast('Network issue', 'The request did not complete. Check your connection and retry.')
    }

    return Promise.reject(error)
  },
)

export default api

function emitHttpToast(summary: string, detail: string): void {
  window.dispatchEvent(new CustomEvent('jobhunter:toast', {
    detail: {
      severity: 'error',
      summary,
      detail,
      life: 5000,
    },
  }))
}

function normalizeValidationMessage(payload: unknown): string | null {
  if (!payload || typeof payload !== 'object') {
    return null
  }

  const errors = (payload as { errors?: Record<string, string[]> }).errors

  if (!errors) {
    return null
  }

  return Object.values(errors).flat()[0] ?? null
}
