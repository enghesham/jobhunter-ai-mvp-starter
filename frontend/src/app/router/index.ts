import { createRouter, createWebHistory } from 'vue-router'

import AppLayout from '@/layouts/AppLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import ApplicationsPage from '@/modules/applications/ApplicationsPage.vue'
import CandidateProfilePage from '@/modules/candidate-profile/CandidateProfilePage.vue'
import DashboardPage from '@/modules/dashboard/DashboardPage.vue'
import JobsPage from '@/modules/jobs/JobsPage.vue'
import JobSourcesPage from '@/modules/job-sources/JobSourcesPage.vue'
import LoginPage from '@/modules/auth/LoginPage.vue'
import MatchesPage from '@/modules/matches/MatchesPage.vue'
import RegisterPage from '@/modules/auth/RegisterPage.vue'
import ResumesPage from '@/modules/resumes/ResumesPage.vue'
import SettingsPage from '@/modules/settings/SettingsPage.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      component: AppLayout,
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
        { path: 'login', component: LoginPage, meta: { title: 'Login' } },
        { path: 'register', component: RegisterPage, meta: { title: 'Register' } },
      ],
    },
  ],
})

router.afterEach((to) => {
  document.title = to.meta.title ? `JobHunter AI | ${String(to.meta.title)}` : 'JobHunter AI'
})

export default router
