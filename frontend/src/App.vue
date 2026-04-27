<template>
  <RouterView />
  <Toast />
  <ConfirmDialog />
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import ConfirmDialog from 'primevue/confirmdialog'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { RouterView } from 'vue-router'

interface ToastEventDetail {
  severity: 'success' | 'info' | 'warn' | 'error'
  summary: string
  detail: string
  life?: number
}

const toast = useToast()

function handleToastEvent(event: Event): void {
  const detail = (event as CustomEvent<ToastEventDetail>).detail

  if (!detail) {
    return
  }

  toast.add({
    severity: detail.severity,
    summary: detail.summary,
    detail: detail.detail,
    life: detail.life ?? 4000,
  })
}

onMounted(() => {
  window.addEventListener('jobhunter:toast', handleToastEvent as EventListener)
})

onUnmounted(() => {
  window.removeEventListener('jobhunter:toast', handleToastEvent as EventListener)
})
</script>
