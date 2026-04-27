<template>
  <div class="flex min-h-screen">
    <aside class="hidden w-76 flex-col border-r border-slate-200 bg-slate-950 px-5 py-6 text-slate-100 lg:flex">
      <div class="mb-8">
        <p class="text-xs uppercase tracking-[0.3em] text-sky-300">JobHunter AI</p>
        <h1 class="mt-2 text-2xl font-semibold">Control Panel</h1>
      </div>

      <nav class="flex-1 space-y-6">
        <section v-for="section in navSections" :key="section.label">
          <p class="mb-3 px-4 text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">{{ section.label }}</p>

          <div class="space-y-2">
            <RouterLink
              v-for="item in section.items"
              :key="item.to"
              :to="item.to"
              class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm text-slate-300 transition hover:bg-slate-900 hover:text-white"
              :class="isActiveRoute(item.to) ? 'bg-sky-500/20 text-white ring-1 ring-sky-400/40' : ''"
            >
              <i :class="['pi text-base', item.icon]" />
              <span>{{ item.label }}</span>
            </RouterLink>
          </div>
        </section>
      </nav>
    </aside>

    <div class="flex flex-1 flex-col">
      <header class="border-b border-slate-200 bg-white/80 px-4 py-4 backdrop-blur lg:px-8">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-sm text-slate-500">Authenticated workspace</p>
            <h2 class="text-xl font-semibold text-slate-900">{{ pageTitle }}</h2>
          </div>

          <div class="flex items-center gap-4">
            <div class="hidden text-right md:block">
              <p class="text-sm font-medium text-slate-900">{{ userName }}</p>
              <p class="text-xs text-slate-500">{{ userEmail }}</p>
            </div>

            <Avatar :label="userInitial" shape="circle" class="bg-sky-100 text-sky-700" />

            <Button
              label="Logout"
              icon="pi pi-sign-out"
              severity="secondary"
              outlined
              :loading="authStore.loading"
              @click="handleLogout"
            />
          </div>
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
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'

import { useAuthStore } from '@/app/stores/authStore'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const navSections = [
  {
    label: 'Main',
    items: [{ label: 'Dashboard', to: '/dashboard', icon: 'pi-home' }],
  },
  {
    label: 'Job Management',
    items: [
      { label: 'Job Sources', to: '/job-sources', icon: 'pi-database' },
      { label: 'Jobs', to: '/jobs', icon: 'pi-briefcase' },
      { label: 'Matches', to: '/matches', icon: 'pi-star' },
      { label: 'Resumes', to: '/resumes', icon: 'pi-file-edit' },
    ],
  },
  {
    label: 'Candidate',
    items: [{ label: 'Candidate Profile', to: '/candidate-profile', icon: 'pi-user' }],
  },
  {
    label: 'Applications',
    items: [{ label: 'Applications', to: '/applications', icon: 'pi-send' }],
  },
  {
    label: 'Settings',
    items: [{ label: 'Settings', to: '/settings', icon: 'pi-cog' }],
  },
]

const pageTitle = computed(() => String(route.meta.title ?? 'Workspace'))
const userName = computed(() => authStore.user?.name ?? 'Authenticated User')
const userEmail = computed(() => authStore.user?.email ?? 'No email loaded')
const userInitial = computed(() => userName.value.charAt(0).toUpperCase())

async function handleLogout(): Promise<void> {
  await authStore.logout()
  await router.push('/login')
}

function isActiveRoute(target: string): boolean {
  return route.path === target || route.path.startsWith(`${target}/`)
}
</script>
