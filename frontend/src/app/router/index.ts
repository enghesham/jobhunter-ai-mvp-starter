import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/app/stores/authStore'

import AppLayout from '@/layouts/AppLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

const ApplicationsPage = () => import('@/modules/applications/pages/ApplicationsPage.vue')
const CandidateProfilePage = () => import('@/modules/candidate-profile/pages/CandidateProfilePage.vue')
const DashboardPage = () => import('@/modules/dashboard/pages/DashboardPage.vue')
const AiQualityPage = () => import('@/modules/developer/pages/AiQualityPage.vue')
const JobsPage = () => import('@/modules/jobs/pages/JobsPage.vue')
const JobSourcesPage = () => import('@/modules/job-sources/pages/JobSourcesPage.vue')
const LoginPage = () => import('@/modules/auth/pages/LoginPage.vue')
const MatchesPage = () => import('@/modules/matches/pages/MatchesPage.vue')
const RegisterPage = () => import('@/modules/auth/pages/RegisterPage.vue')
const ResumesPage = () => import('@/modules/resumes/pages/ResumesPage.vue')
const SettingsPage = () => import('@/modules/settings/SettingsPage.vue')

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
        { path: 'developer/ai-quality', component: AiQualityPage, meta: { title: 'AI Quality' } },
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
