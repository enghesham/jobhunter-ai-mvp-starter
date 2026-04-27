<template>
  <div class="space-y-6">
    <div>
      <p class="text-xs font-semibold uppercase tracking-[0.3em] text-sky-600">Welcome Back</p>
      <h1 class="mt-3 text-3xl font-semibold text-slate-900">Login</h1>
      <p class="mt-2 text-sm text-slate-600">PrimeVue form placeholders only for this phase.</p>
    </div>

    <div class="space-y-4">
      <FloatLabel variant="on">
        <InputText id="login-email" class="w-full" />
        <label for="login-email">Email</label>
      </FloatLabel>

      <FloatLabel variant="on">
        <Password id="login-password" class="w-full" input-class="w-full" toggle-mask :feedback="false" />
        <label for="login-password">Password</label>
      </FloatLabel>

      <Button label="Login" icon="pi pi-sign-in" class="w-full" />
    </div>
  </div>
</template>

<script setup lang="ts">
import Button from 'primevue/button'
import FloatLabel from 'primevue/floatlabel'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
</script>
