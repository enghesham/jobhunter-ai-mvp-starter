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

      <div
        v-if="setupLocked"
        class="mb-6 rounded-3xl border border-amber-300/25 bg-amber-300/10 p-4 text-amber-50"
        :class="sidebarCollapsed ? 'px-3 text-center' : ''"
      >
        <i class="pi pi-lock text-amber-200" />
        <div v-if="!sidebarCollapsed" class="mt-3">
          <p class="text-sm font-semibold">Workspace locked</p>
          <p class="mt-1 text-xs leading-5 text-amber-100/80">
            Finish Guided Setup first. It creates your Career Profile and Job Paths, then unlocks the rest of the app.
          </p>
          <Button
            label="Continue setup"
            icon="pi pi-arrow-right"
            size="small"
            class="mt-3 w-full"
            severity="warn"
            @click="goToOnboarding"
          />
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
              v-slot="{ href, navigate }"
              custom
            >
              <a
                :href="href"
                class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm text-slate-300 transition hover:bg-slate-900 hover:text-white"
                :class="[
                  isActiveRoute(item.to) ? 'bg-sky-500/20 text-white ring-1 ring-sky-400/40' : '',
                  isNavigationLocked(item) ? 'cursor-not-allowed opacity-60 hover:bg-slate-950 hover:text-slate-300' : '',
                ]"
                :title="navItemTitle(item)"
                :aria-disabled="isNavigationLocked(item)"
                @click="handleSidebarNavigation($event, item, navigate)"
              >
                <i :class="['pi text-base', item.icon]" />
                <span v-if="!sidebarCollapsed" class="min-w-0 flex-1">
                  <span class="flex items-center gap-2">
                    <span class="truncate">{{ item.label }}</span>
                    <span
                      v-if="item.badge"
                      :class="[
                        'rounded-full px-2 py-0.5 text-[0.62rem] font-semibold uppercase tracking-[0.14em]',
                        badgeClass(item.badgeTone),
                      ]"
                    >
                      {{ item.badge }}
                    </span>
                    <span
                      v-if="isNavigationLocked(item)"
                      class="rounded-full bg-amber-400/15 px-2 py-0.5 text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-amber-200 ring-1 ring-amber-300/25"
                    >
                      Locked
                    </span>
                  </span>
                  <span v-if="isNavigationLocked(item)" class="mt-0.5 block truncate text-xs text-amber-100/60">Complete Guided Setup first</span>
                  <span v-else-if="item.help" class="mt-0.5 block truncate text-xs text-slate-500">{{ item.help }}</span>
                </span>
                <i v-if="sidebarCollapsed && isNavigationLocked(item)" class="pi pi-lock ml-auto text-xs text-amber-200" />
              </a>
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

      <section v-if="setupLocked" class="border-b border-amber-200 bg-gradient-to-r from-amber-50 via-orange-50 to-white px-4 py-4 lg:px-8">
        <div class="flex flex-col gap-4 rounded-3xl border border-amber-200 bg-white/80 p-4 shadow-sm shadow-amber-100/60 md:flex-row md:items-center md:justify-between">
          <div class="flex gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
              <i class="pi pi-lock" />
            </div>
            <div>
              <p class="text-sm font-semibold text-amber-950">Complete Guided Setup to unlock your workspace</p>
              <p class="mt-1 max-w-3xl text-sm leading-6 text-amber-900/75">
                The rest of the app is intentionally locked until you create a Career Profile and choose Job Paths. This keeps recommendations, matches, and apply packages accurate.
              </p>
              <p class="mt-2 text-xs font-medium uppercase tracking-[0.18em] text-amber-700">{{ onboardingStepLabel }}</p>
            </div>
          </div>

          <Button label="Continue Guided Setup" icon="pi pi-arrow-right" severity="warn" @click="goToOnboarding" />
        </div>
      </section>

      <main class="flex-1 px-4 py-6 lg:px-8">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import type { MenuItem } from 'primevue/menuitem'
import { useToast } from 'primevue/usetoast'

import { useAuthStore } from '@/app/stores/authStore'
import { useOnboardingStore } from '@/app/stores/onboardingStore'

interface SidebarItem {
  label: string
  to: string
  icon: string
  badge?: string
  badgeTone?: string
  help?: string
  availableDuringOnboarding?: boolean
}

interface SidebarSection {
  label: string
  items: SidebarItem[]
}

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const onboardingStore = useOnboardingStore()
const toast = useToast()
const sidebarCollapsed = ref(false)
const mobileSidebarOpen = ref(false)
const userMenu = ref()

const navSections: SidebarSection[] = [
  {
    label: 'Main',
    items: [
      { label: 'Dashboard', to: '/dashboard', icon: 'pi-home' },
      { label: 'Guided Setup', to: '/onboarding', icon: 'pi-compass', availableDuringOnboarding: true },
      { label: 'Opportunities', to: '/opportunities', icon: 'pi-sparkles' },
    ],
  },
  {
    label: 'Job Management',
    items: [
      { label: 'Job Sources', to: '/job-sources', icon: 'pi-database', badge: 'Setup', badgeTone: 'slate', help: 'Only when collecting/importing jobs' },
      { label: 'Raw Jobs', to: '/jobs', icon: 'pi-briefcase', badge: 'Legacy', badgeTone: 'amber', help: 'Replaced by Opportunities flow' },
      { label: 'Best Matches', to: '/matches', icon: 'pi-star' },
      { label: 'Apply Packages', to: '/apply-packages', icon: 'pi-file-edit' },
      { label: 'Resumes', to: '/resumes', icon: 'pi-file', badge: 'Optional', badgeTone: 'slate', help: 'Artifacts are shown in packages' },
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
    items: [{ label: 'AI Quality', to: '/developer/ai-quality', icon: 'pi-chart-line', badge: 'Dev', badgeTone: 'sky', help: 'Diagnostics only' }],
  },
]

const pageTitle = computed(() => String(route.meta.title ?? 'Workspace'))
const userName = computed(() => authStore.user?.name ?? 'Authenticated User')
const userEmail = computed(() => authStore.user?.email ?? 'No email loaded')
const userInitial = computed(() => userName.value.charAt(0).toUpperCase())
const setupLocked = computed(() => onboardingStore.initialized && !onboardingStore.isCompleted)
const onboardingStepLabel = computed(() => {
  const step = onboardingStore.state?.current_step

  switch (step) {
    case 'career_profile':
      return 'Current step: build your Career Profile'
    case 'job_paths':
      return 'Current step: choose your Job Paths'
    case 'completed':
      return 'Setup is almost complete'
    default:
      return 'Current step: Guided Setup'
  }
})
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
        command: () => goToWorkspaceRoute('/candidate-profile'),
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

onMounted(() => {
  if (!onboardingStore.initialized) {
    void onboardingStore.fetchOnboarding()
  }
})

async function handleLogout(): Promise<void> {
  await authStore.logout()
  await router.push('/login')
}

function isActiveRoute(target: string): boolean {
  return route.path === target || route.path.startsWith(`${target}/`)
}

function badgeClass(tone?: string): string {
  switch (tone) {
    case 'amber':
      return 'bg-amber-400/15 text-amber-200 ring-1 ring-amber-300/25'
    case 'sky':
      return 'bg-sky-400/15 text-sky-200 ring-1 ring-sky-300/25'
    default:
      return 'bg-slate-700 text-slate-300 ring-1 ring-slate-600'
  }
}

function isNavigationLocked(item: SidebarItem): boolean {
  return setupLocked.value && !item.availableDuringOnboarding
}

function navItemTitle(item: SidebarItem): string | undefined {
  if (isNavigationLocked(item)) {
    return `${item.label} is locked until Guided Setup is complete`
  }

  return sidebarCollapsed ? item.label : undefined
}

function handleSidebarNavigation(event: MouseEvent, item: SidebarItem, navigate: (event?: MouseEvent) => void): void {
  mobileSidebarOpen.value = false

  if (isNavigationLocked(item)) {
    event.preventDefault()
    toast.add({
      severity: 'warn',
      summary: 'Guided Setup required',
      detail: 'Complete Guided Setup first to unlock this screen.',
      life: 4500,
    })
    void router.push('/onboarding')
    return
  }

  navigate(event)
}

function goToOnboarding(): void {
  mobileSidebarOpen.value = false
  void router.push('/onboarding')
}

function goToWorkspaceRoute(path: string): void {
  if (setupLocked.value && path !== '/onboarding') {
    toast.add({
      severity: 'warn',
      summary: 'Guided Setup required',
      detail: 'Complete Guided Setup first to unlock your workspace.',
      life: 4500,
    })
    void router.push('/onboarding')
    return
  }

  void router.push(path)
}

function toggleUserMenu(event: Event): void {
  userMenu.value?.toggle(event)
}
</script>
