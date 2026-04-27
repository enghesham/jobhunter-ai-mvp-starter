import axios, { type AxiosInstance } from 'axios'

import router from '@/app/router'
import { useAuthStore } from '@/app/stores/auth'

const TOKEN_KEY = 'jobhunter_access_token'

const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const authStore = useAuthStore()
  const token = authStore.token || localStorage.getItem(TOKEN_KEY)

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      const authStore = useAuthStore()
      authStore.clearAuth()

      if (router.currentRoute.value.path !== '/login') {
        await router.push('/login')
      }
    }

    return Promise.reject(error)
  },
)

export default api
