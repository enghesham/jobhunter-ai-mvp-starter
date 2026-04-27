<template>
  <div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white/90 p-8 shadow-xl shadow-slate-200/60 backdrop-blur">
      <RouterView />
    </div>
  </div>
</template>

<script setup lang="ts">
import { RouterView } from 'vue-router'
</script>
