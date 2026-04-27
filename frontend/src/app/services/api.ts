import axios, { type AxiosInstance } from 'axios'

import router from '@/app/router'
import { storageKeys } from '@/shared/utils/storage'

const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
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

      if (router.currentRoute.value.path !== '/login') {
        await router.push({ path: '/login', query: { redirect: router.currentRoute.value.fullPath } })
      }
    }

    return Promise.reject(error)
  },
)

export default api
