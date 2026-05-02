import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import { getOnboarding } from '@/modules/onboarding/services/onboardingApi'
import type { OnboardingPayload } from '@/modules/onboarding/types'

export const useOnboardingStore = defineStore('onboarding', () => {
  const payload = ref<OnboardingPayload | null>(null)
  const loading = ref(false)
  const initialized = ref(false)

  const state = computed(() => payload.value?.state ?? null)
  const isCompleted = computed(() => Boolean(state.value?.is_completed))

  async function fetchOnboarding(force = false): Promise<OnboardingPayload | null> {
    if (initialized.value && !force) {
      return payload.value
    }

    loading.value = true

    try {
      payload.value = await getOnboarding()
      initialized.value = true
      return payload.value
    } catch {
      initialized.value = true
      return null
    } finally {
      loading.value = false
    }
  }

  function setPayload(nextPayload: OnboardingPayload): void {
    payload.value = nextPayload
    initialized.value = true
  }

  function reset(): void {
    payload.value = null
    initialized.value = false
  }

  return {
    payload,
    state,
    loading,
    initialized,
    isCompleted,
    fetchOnboarding,
    setPayload,
    reset,
  }
})
