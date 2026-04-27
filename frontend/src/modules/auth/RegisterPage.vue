<template>
  <div class="space-y-6">
    <div>
      <p class="text-xs font-semibold uppercase tracking-[0.3em] text-emerald-600">Get Started</p>
      <h1 class="mt-3 text-3xl font-semibold text-slate-900">Register</h1>
      <p class="mt-2 text-sm text-slate-600">Authentication screens are placeholders for now.</p>
    </div>

    <div class="space-y-4">
      <FloatLabel variant="on">
        <InputText id="register-name" class="w-full" />
        <label for="register-name">Full Name</label>
      </FloatLabel>

      <FloatLabel variant="on">
        <InputText id="register-email" class="w-full" />
        <label for="register-email">Email</label>
      </FloatLabel>

      <FloatLabel variant="on">
        <Password id="register-password" class="w-full" input-class="w-full" toggle-mask :feedback="false" />
        <label for="register-password">Password</label>
      </FloatLabel>

      <Button label="Create Account" icon="pi pi-user-plus" class="w-full" />
    </div>
  </div>
</template>

<script setup lang="ts">
import Button from 'primevue/button'
import FloatLabel from 'primevue/floatlabel'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
</script>
