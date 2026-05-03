<template>
  <div class="flex min-h-screen">
    <div
      v-if="mobileSidebarOpen"
      class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden"
      @click="mobileSidebarOpen = false"
    />

    <aside
      class="fixed inset-y-0 left-0 z-50 flex w-76 flex-col border-r border-slate-200 bg-slate-950 px-5 py-6 text-slate-100 transition-transform duration-200 lg:static lg:z-auto"
      :class="[mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', sidebarCollapsed ? 'lg:w-24 lg:px-3' : 'lg:w-76']"
    >
      <div class="mb-8">
        <div class="flex items-center justify-between gap-3">
          <div v-if="!sidebarCollapsed" class="min-w-0">
            <p class="text-xs uppercase tracking-[0.3em] text-sky-300">JobHunter AI</p>
            <h1 class="mt-2 text-2xl font-semibold">Control Panel</h1>
          </div>
          <Button
            icon="pi pi-angle-double-left"
            severity="secondary"
            text
            rounded
            class="hidden text-slate-300 lg:inline-flex"
            :class="sidebarCollapsed ? 'rotate-180' : ''"
            @click="sidebarCollapsed = !sidebarCollapsed"
          />
          <Button icon="pi pi-times" severity="secondary" text rounded class="text-slate-300 lg:hidden" @click="mobileSidebarOpen = false" />
        </div>
      </div>

      <nav class="flex-1 space-y-6">
        <section v-for="section in navSections" :key="section.label">
          <p v-if="!sidebarCollapsed" class="mb-3 px-4 text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">{{ section.label }}</p>

          <div class="space-y-2">
            <RouterLink
              v-for="item in section.items"
              :key="item.to"
              :to="item.to"
              class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm text-slate-300 transition hover:bg-slate-900 hover:text-white"
              :class="isActiveRoute(item.to) ? 'bg-sky-500/20 text-white ring-1 ring-sky-400/40' : ''"
              :title="sidebarCollapsed ? item.label : undefined"
              @click="mobileSidebarOpen = false"
            >
              <i :class="['pi text-base', item.icon]" />
              <span v-if="!sidebarCollapsed">{{ item.label }}</span>
            </RouterLink>
          </div>
        </section>
      </nav>
    </aside>

    <div class="flex flex-1 flex-col">
      <header class="border-b border-slate-200 bg-white/80 px-4 py-4 backdrop-blur lg:px-8">
        <div class="flex items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <Button icon="pi pi-bars" severity="secondary" text rounded class="lg:hidden" @click="mobileSidebarOpen = true" />
            <div>
            <p class="text-sm text-slate-500">Authenticated workspace</p>
            <h2 class="text-xl font-semibold text-slate-900">{{ pageTitle }}</h2>
            </div>
          </div>

          <div class="flex items-center gap-4">
            <Menu ref="userMenu" :model="userMenuItems" popup />
            <button
              type="button"
              class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm shadow-slate-200/50 transition hover:border-sky-300 hover:bg-sky-50"
              @click="toggleUserMenu"
            >
              <div class="hidden text-right md:block">
                <p class="text-sm font-medium text-slate-900">{{ userName }}</p>
                <p class="text-xs text-slate-500">{{ userEmail }}</p>
              </div>

              <Avatar :label="userInitial" shape="circle" class="bg-sky-100 text-sky-700" />
              <i class="pi pi-chevron-down text-xs text-slate-500" />
            </button>
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
import { computed, ref } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import type { MenuItem } from 'primevue/menuitem'

import { useAuthStore } from '@/app/stores/authStore'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const sidebarCollapsed = ref(false)
const mobileSidebarOpen = ref(false)
const userMenu = ref()

const navSections = [
  {
    label: 'Main',
    items: [
      { label: 'Dashboard', to: '/dashboard', icon: 'pi-home' },
      { label: 'Guided Setup', to: '/onboarding', icon: 'pi-compass' },
      { label: 'Opportunities', to: '/opportunities', icon: 'pi-sparkles' },
    ],
  },
  {
    label: 'Job Management',
    items: [
      { label: 'Job Sources', to: '/job-sources', icon: 'pi-database' },
      { label: 'Jobs', to: '/jobs', icon: 'pi-briefcase' },
      { label: 'Best Matches', to: '/matches', icon: 'pi-star' },
      { label: 'Apply Packages', to: '/apply-packages', icon: 'pi-file-edit' },
      { label: 'Resumes', to: '/resumes', icon: 'pi-file' },
    ],
  },
  {
    label: 'Candidate',
    items: [{ label: 'My Career Profile', to: '/candidate-profile', icon: 'pi-user' }],
  },
  {
    label: 'Applications',
    items: [{ label: 'Applications', to: '/applications', icon: 'pi-send' }],
  },
  {
    label: 'Settings',
    items: [{ label: 'Settings', to: '/settings', icon: 'pi-cog' }],
  },
  {
    label: 'Developer',
    items: [{ label: 'AI Quality', to: '/developer/ai-quality', icon: 'pi-chart-line' }],
  },
]

const pageTitle = computed(() => String(route.meta.title ?? 'Workspace'))
const userName = computed(() => authStore.user?.name ?? 'Authenticated User')
const userEmail = computed(() => authStore.user?.email ?? 'No email loaded')
const userInitial = computed(() => userName.value.charAt(0).toUpperCase())
const userMenuItems = computed<MenuItem[]>(() => [
  {
    label: userName.value,
    items: [
      {
        label: userEmail.value,
        icon: 'pi pi-envelope',
        disabled: true,
      },
    ],
  },
  {
    label: 'Workspace',
    items: [
      {
        label: 'My Career Profile',
        icon: 'pi pi-user',
        command: () => router.push('/candidate-profile'),
      },
      {
        label: 'Logout',
        icon: 'pi pi-sign-out',
        command: () => {
          void handleLogout()
        },
      },
    ],
  },
])

async function handleLogout(): Promise<void> {
  await authStore.logout()
  await router.push('/login')
}

function isActiveRoute(target: string): boolean {
  return route.path === target || route.path.startsWith(`${target}/`)
}

function toggleUserMenu(event: Event): void {
  userMenu.value?.toggle(event)
}
</script>
