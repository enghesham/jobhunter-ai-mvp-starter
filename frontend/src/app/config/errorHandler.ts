import type { App } from 'vue'

function emitToast(summary: string, detail: string, severity: 'error' | 'warn' = 'error'): void {
  window.dispatchEvent(new CustomEvent('jobhunter:toast', {
    detail: {
      severity,
      summary,
      detail,
      life: 5000,
    },
  }))
}

export function registerGlobalErrorHandler(app: App<Element>): void {
  app.config.errorHandler = (error, instance, info) => {
    console.error('Vue error', { error, info, instance })
    emitToast('Unexpected error', 'An unexpected interface error occurred. Please retry the action.')
  }

  window.addEventListener('error', (event) => {
    console.error('Window error', event.error ?? event.message)
    emitToast('Unexpected error', 'The application hit an unexpected browser error.')
  })

  window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection', event.reason)
    emitToast('Request failed', 'A background request failed unexpectedly. Please retry.')
  })
}
