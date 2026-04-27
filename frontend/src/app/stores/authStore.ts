import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import { loginRequest, logoutRequest, meRequest, registerRequest } from '@/modules/auth/services/authApi'
import type { AuthUser, LoginInput, RegisterInput } from '@/modules/auth/types'
import { storageKeys } from '@/shared/utils/storage'

let unauthorizedListenerRegistered = false

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const token = ref<string | null>(localStorage.getItem(storageKeys.authToken))
  const loading = ref(false)
  const initialized = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value && user.value))

  function persistToken(nextToken: string | null): void {
    token.value = nextToken

    if (nextToken) {
      localStorage.setItem(storageKeys.authToken, nextToken)
      return
    }

    localStorage.removeItem(storageKeys.authToken)
  }

  function clearSession(): void {
    user.value = null
    persistToken(null)
  }

  if (typeof window !== 'undefined' && !unauthorizedListenerRegistered) {
    window.addEventListener('jobhunter:unauthorized', () => {
      clearSession()
      initialized.value = true
    })
    unauthorizedListenerRegistered = true
  }

  function setSession(nextUser: AuthUser, nextToken: string): void {
    user.value = nextUser
    persistToken(nextToken)
  }

  async function login(input: LoginInput): Promise<void> {
    loading.value = true

    try {
      const payload = await loginRequest(input)
      setSession(payload.user, payload.token)
      initialized.value = true
    } finally {
      loading.value = false
    }
  }

  async function register(input: RegisterInput): Promise<void> {
    loading.value = true

    try {
      const payload = await registerRequest(input)
      setSession(payload.user, payload.token)
      initialized.value = true
    } finally {
      loading.value = false
    }
  }

  async function fetchMe(): Promise<AuthUser | null> {
    if (!token.value) {
      clearSession()
      initialized.value = true
      return null
    }

    loading.value = true

    try {
      const me = await meRequest()
      user.value = me
      initialized.value = true

      return me
    } catch {
      clearSession()
      initialized.value = true
      return null
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    loading.value = true

    try {
      if (token.value) {
        await logoutRequest()
      }
    } catch {
      // Local cleanup is enough for fallback behavior.
    } finally {
      clearSession()
      initialized.value = true
      loading.value = false
    }
  }

  async function restoreSession(): Promise<void> {
    if (initialized.value) {
      return
    }

    if (!token.value) {
      initialized.value = true
      return
    }

    await fetchMe()
  }

  return {
    user,
    token,
    loading,
    initialized,
    isAuthenticated,
    login,
    register,
    fetchMe,
    logout,
    restoreSession,
    clearSession,
  }
})
