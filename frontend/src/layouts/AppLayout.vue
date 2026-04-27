<template>
  <div class="flex min-h-screen">
    <aside class="hidden w-72 flex-col border-r border-slate-200 bg-slate-950 px-5 py-6 text-slate-100 lg:flex">
      <div class="mb-8">
        <p class="text-xs uppercase tracking-[0.3em] text-sky-300">JobHunter AI</p>
        <h1 class="mt-2 text-2xl font-semibold">Control Panel</h1>
      </div>

      <nav class="flex-1 space-y-2">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm text-slate-300 transition hover:bg-slate-900 hover:text-white"
          active-class="bg-sky-500/20 text-white ring-1 ring-sky-400/40"
        >
          <i :class="['pi text-base', item.icon]" />
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>
    </aside>

    <div class="flex flex-1 flex-col">
      <header class="border-b border-slate-200 bg-white/80 px-4 py-4 backdrop-blur lg:px-8">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-sm text-slate-500">Frontend bootstrap</p>
            <h2 class="text-xl font-semibold text-slate-900">{{ pageTitle }}</h2>
          </div>

          <Button
            label="Logout"
            icon="pi pi-sign-out"
            severity="secondary"
            outlined
            @click="handleLogoutPlaceholder"
          />
        </div>
      </header>

      <main class="flex-1 px-4 py-6 lg:px-8">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import Button from 'primevue/button'

const route = useRoute()

const navItems = [
  { label: 'Dashboard', to: '/dashboard', icon: 'pi-home' },
  { label: 'Job Sources', to: '/job-sources', icon: 'pi-database' },
  { label: 'Jobs', to: '/jobs', icon: 'pi-briefcase' },
  { label: 'Candidate Profile', to: '/candidate-profile', icon: 'pi-user' },
  { label: 'Matches', to: '/matches', icon: 'pi-star' },
  { label: 'Resumes', to: '/resumes', icon: 'pi-file-edit' },
  { label: 'Applications', to: '/applications', icon: 'pi-send' },
  { label: 'Settings', to: '/settings', icon: 'pi-cog' },
]

const pageTitle = computed(() => String(route.meta.title ?? 'Workspace'))

function handleLogoutPlaceholder(): void {
  window.alert('Logout wiring will be implemented in a later phase.')
}
</script>
