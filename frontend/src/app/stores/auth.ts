import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

const TOKEN_KEY = 'jobhunter_access_token'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(TOKEN_KEY))

  const isAuthenticated = computed(() => Boolean(token.value))

  function setToken(nextToken: string | null): void {
    token.value = nextToken

    if (nextToken) {
      localStorage.setItem(TOKEN_KEY, nextToken)
      return
    }

    localStorage.removeItem(TOKEN_KEY)
  }

  function clearAuth(): void {
    setToken(null)
  }

  return {
    token,
    isAuthenticated,
    setToken,
    clearAuth,
  }
})
