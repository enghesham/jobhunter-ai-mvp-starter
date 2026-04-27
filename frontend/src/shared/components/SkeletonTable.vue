<template>
  <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <Skeleton height="2.75rem" class="w-full max-w-sm rounded-2xl" />
      <Skeleton height="2.75rem" class="w-40 rounded-2xl" />
    </div>

    <div class="mt-6 space-y-3">
      <div v-for="row in rows" :key="row" class="grid gap-3 rounded-2xl border border-slate-100 bg-slate-50/80 p-4" :style="gridStyle">
        <Skeleton v-for="column in columns" :key="column" height="1.1rem" class="rounded-lg" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Skeleton from 'primevue/skeleton'

const props = withDefaults(defineProps<{
  rows?: number
  columns?: number
}>(), {
  rows: 6,
  columns: 5,
})

const gridStyle = computed(() => ({
  gridTemplateColumns: `repeat(${props.columns}, minmax(0, 1fr))`,
}))
</script>
