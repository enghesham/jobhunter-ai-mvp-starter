import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/app/stores/authStore'

import AppLayout from '@/layouts/AppLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import ApplicationsPage from '@/modules/applications/pages/ApplicationsPage.vue'
import CandidateProfilePage from '@/modules/candidate-profile/pages/CandidateProfilePage.vue'
import DashboardPage from '@/modules/dashboard/pages/DashboardPage.vue'
import JobsPage from '@/modules/jobs/pages/JobsPage.vue'
import JobSourcesPage from '@/modules/job-sources/pages/JobSourcesPage.vue'
import LoginPage from '@/modules/auth/pages/LoginPage.vue'
import MatchesPage from '@/modules/matches/pages/MatchesPage.vue'
import RegisterPage from '@/modules/auth/pages/RegisterPage.vue'
import ResumesPage from '@/modules/resumes/pages/ResumesPage.vue'
import SettingsPage from '@/modules/settings/SettingsPage.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/dashboard' },
        { path: 'dashboard', component: DashboardPage, meta: { title: 'Dashboard' } },
        { path: 'job-sources', component: JobSourcesPage, meta: { title: 'Job Sources' } },
        { path: 'jobs', component: JobsPage, meta: { title: 'Jobs' } },
        { path: 'candidate-profile', component: CandidateProfilePage, meta: { title: 'Candidate Profile' } },
        { path: 'matches', component: MatchesPage, meta: { title: 'Matches' } },
        { path: 'resumes', component: ResumesPage, meta: { title: 'Resumes' } },
        { path: 'applications', component: ApplicationsPage, meta: { title: 'Applications' } },
        { path: 'settings', component: SettingsPage, meta: { title: 'Settings' } },
      ],
    },
    {
      path: '/',
      component: AuthLayout,
      children: [
        { path: 'login', component: LoginPage, meta: { title: 'Login', guestOnly: true } },
        { path: 'register', component: RegisterPage, meta: { title: 'Register', guestOnly: true } },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  if (!authStore.initialized) {
    await authStore.restoreSession()
  }

  if (to.meta.guestOnly && authStore.isAuthenticated) {
    return { path: '/dashboard' }
  }

  if (to.meta.requiresAuth && !authStore.token) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  if (to.meta.requiresAuth && authStore.token && !authStore.isAuthenticated) {
    const user = await authStore.fetchMe()

    if (!user) {
      return { path: '/login', query: { redirect: to.fullPath } }
    }
  }

  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `JobHunter AI | ${String(to.meta.title)}` : 'JobHunter AI'
})

export default router
