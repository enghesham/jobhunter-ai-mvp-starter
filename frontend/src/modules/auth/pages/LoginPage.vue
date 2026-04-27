<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Welcome Back"
      title="Login"
      description="Sign in to access your job tracking workspace, resume drafts, and matching pipeline."
    />

    <Message v-if="apiError" severity="error" :closable="false">
      {{ apiError }}
    </Message>

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div class="space-y-2">
        <FloatLabel variant="on">
          <InputText id="login-email" v-model="form.email" class="w-full" :invalid="Boolean(errors.email)" />
          <label for="login-email">Email</label>
        </FloatLabel>
        <FormError :message="errors.email" />
      </div>

      <div class="space-y-2">
        <FloatLabel variant="on">
          <Password
            id="login-password"
            v-model="form.password"
            class="w-full"
            input-class="w-full"
            toggle-mask
            :feedback="false"
            :invalid="Boolean(errors.password)"
          />
          <label for="login-password">Password</label>
        </FloatLabel>
        <FormError :message="errors.password" />
      </div>

      <LoadingButton
        class="w-full"
        type="submit"
        label="Login"
        loading-label="Signing in..."
        icon="pi pi-sign-in"
        :loading="authStore.loading"
      />
    </form>

    <p class="text-sm text-slate-600">
      Don&apos;t have an account?
      <RouterLink class="font-medium text-sky-600 hover:text-sky-700" to="/register">Create one</RouterLink>
    </p>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import FloatLabel from 'primevue/floatlabel'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'

import { useAuthStore } from '@/app/stores/authStore'
import FormError from '@/shared/components/FormError.vue'
import LoadingButton from '@/shared/components/LoadingButton.vue'
import PageHeader from '@/shared/components/PageHeader.vue'

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()

const form = reactive({
  email: '',
  password: '',
})

const errors = reactive<Record<string, string | null>>({
  email: null,
  password: null,
})

const apiError = ref<string | null>(null)

function validate(): boolean {
  errors.email = form.email ? null : 'Email is required.'
  errors.password = form.password ? null : 'Password is required.'

  return Object.values(errors).every((value) => !value)
}

async function handleSubmit(): Promise<void> {
  apiError.value = null

  if (!validate()) {
    return
  }

  try {
    await authStore.login({
      email: form.email,
      password: form.password,
      device_name: 'frontend-web',
    })

    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard'
    await router.push(redirect)
  } catch (error) {
    apiError.value = axios.isAxiosError(error)
      ? String(error.response?.data?.message ?? 'Login failed.')
      : 'Login failed.'
  }
}
</script>
