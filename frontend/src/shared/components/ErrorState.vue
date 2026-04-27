<template>
  <section class="rounded-3xl border border-red-200 bg-red-50 px-6 py-8 shadow-sm shadow-red-100/50">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div class="flex items-start gap-4">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-100 text-red-700">
          <i class="pi pi-exclamation-triangle text-lg" />
        </div>
        <div>
          <h3 class="text-lg font-semibold text-slate-900">{{ title }}</h3>
          <p class="mt-1 text-sm leading-6 text-slate-700">{{ message }}</p>
        </div>
      </div>

      <div v-if="$slots.actions" class="flex flex-wrap gap-3">
        <slot name="actions" />
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
  message: string
}>(), {
  title: 'Something went wrong',
})
</script>
