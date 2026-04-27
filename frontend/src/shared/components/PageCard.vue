<template>
  <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/60">
    <div class="mb-4">
      <p class="text-xs font-semibold uppercase tracking-[0.25em] text-sky-600">{{ eyebrow }}</p>
      <h3 class="mt-2 text-2xl font-semibold text-slate-900">{{ title }}</h3>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ description }}</p>
    </div>

    <slot />
  </section>
</template>

<script setup lang="ts">
defineProps<{
  eyebrow: string
  title: string
  description: string
}>()
</script>
