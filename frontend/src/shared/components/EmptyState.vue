<template>
  <section class="rounded-3xl border border-dashed border-slate-300 bg-white/80 px-6 py-12 text-center shadow-sm shadow-slate-200/60">
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
      <i :class="['pi text-xl', icon]" />
    </div>
    <h3 class="mt-5 text-xl font-semibold text-slate-900">{{ title }}</h3>
    <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ description }}</p>

    <div v-if="$slots.actions" class="mt-6 flex flex-wrap justify-center gap-3">
      <slot name="actions" />
    </div>
  </section>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  description: string
  icon?: string
}>(), {
  icon: 'pi-inbox',
})
</script>
