<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Get Started"
      title="Register"
      description="Create your account to manage sources, jobs, matches, resumes, and applications."
    />

    <Message v-if="apiError" severity="error" :closable="false">
      {{ apiError }}
    </Message>

    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div class="space-y-2">
        <FloatLabel variant="on">
          <InputText id="register-name" v-model="form.name" class="w-full" :invalid="Boolean(errors.name)" />
          <label for="register-name">Name</label>
        </FloatLabel>
        <FormError :message="errors.name" />
      </div>

      <div class="space-y-2">
        <FloatLabel variant="on">
          <InputText id="register-email" v-model="form.email" class="w-full" :invalid="Boolean(errors.email)" />
          <label for="register-email">Email</label>
        </FloatLabel>
        <FormError :message="errors.email" />
      </div>

      <div class="space-y-2">
        <FloatLabel variant="on">
          <Password
            id="register-password"
            v-model="form.password"
            class="w-full"
            input-class="w-full"
            toggle-mask
            :feedback="false"
            :invalid="Boolean(errors.password)"
          />
          <label for="register-password">Password</label>
        </FloatLabel>
        <FormError :message="errors.password" />
      </div>

      <div class="space-y-2">
        <FloatLabel variant="on">
          <Password
            id="register-password-confirmation"
            v-model="form.password_confirmation"
            class="w-full"
            input-class="w-full"
            toggle-mask
            :feedback="false"
            :invalid="Boolean(errors.password_confirmation)"
          />
          <label for="register-password-confirmation">Confirm Password</label>
        </FloatLabel>
        <FormError :message="errors.password_confirmation" />
      </div>

      <LoadingButton
        class="w-full"
        type="submit"
        label="Create Account"
        loading-label="Creating account..."
        icon="pi pi-user-plus"
        :loading="authStore.loading"
      />
    </form>

    <p class="text-sm text-slate-600">
      Already registered?
      <RouterLink class="font-medium text-sky-600 hover:text-sky-700" to="/login">Login here</RouterLink>
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
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const errors = reactive<Record<string, string | null>>({
  name: null,
  email: null,
  password: null,
  password_confirmation: null,
})

const apiError = ref<string | null>(null)

function validate(): boolean {
  errors.name = form.name ? null : 'Name is required.'
  errors.email = form.email ? null : 'Email is required.'
  errors.password = form.password.length >= 8 ? null : 'Password must be at least 8 characters.'
  errors.password_confirmation = form.password_confirmation === form.password
    ? null
    : 'Password confirmation must match the password.'

  return Object.values(errors).every((value) => !value)
}

async function handleSubmit(): Promise<void> {
  apiError.value = null

  if (!validate()) {
    return
  }

  try {
    await authStore.register({ ...form })

    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard'
    await router.push(redirect)
  } catch (error) {
    apiError.value = axios.isAxiosError(error)
      ? String(error.response?.data?.message ?? 'Registration failed.')
      : 'Registration failed.'
  }
}
</script>
