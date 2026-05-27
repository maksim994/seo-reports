import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSettingsStore } from '@/stores/settings'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/auth/LoginPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/pages/auth/RegisterPage.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/projects' },
        {
          path: 'projects',
          name: 'projects',
          component: () => import('@/pages/projects/ProjectsPage.vue'),
        },
        {
          path: 'projects/:id/generate',
          name: 'project-generate',
          component: () => import('@/pages/projects/ReportGeneratorPage.vue'),
        },
        {
          path: 'projects/:id/work',
          name: 'project-work',
          component: () => import('@/pages/projects/ProjectWorkItemsPage.vue'),
        },
        {
          path: 'integrations',
          name: 'integrations',
          component: () => import('@/pages/integrations/IntegrationsPage.vue'),
        },
        {
          path: 'templates',
          name: 'templates',
          component: () => import('@/pages/templates/TemplatesPage.vue'),
        },
        {
          path: 'templates/:id/edit',
          name: 'template-edit',
          component: () => import('@/pages/templates/TemplateEditorPage.vue'),
        },
        {
          path: 'reports',
          name: 'reports',
          component: () => import('@/pages/reports/ReportsPage.vue'),
        },
        {
          path: 'schedules',
          name: 'schedules',
          component: () => import('@/pages/schedules/SchedulesPage.vue'),
        },
        {
          path: 'admin/users',
          name: 'admin-users',
          component: () => import('@/pages/admin/AdminUsersPage.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'admin/settings',
          name: 'admin-settings',
          component: () => import('@/pages/admin/AdminSettingsPage.vue'),
          meta: { requiresAdmin: true },
        },
        {
          path: 'admin/projects',
          name: 'admin-projects',
          component: () => import('@/pages/admin/AdminProjectsPage.vue'),
          meta: { requiresAdmin: true },
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const settings = useSettingsStore()

  if (!settings.publicSettings) {
    await settings.fetchPublicSettings()
  }

  if (!auth.initialized) {
    await auth.fetchUser()
  }

  if (to.name === 'register' && settings.publicSettings && !settings.publicSettings.registration_enabled) {
    return { name: 'login' }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.requiresAdmin && !auth.user?.is_admin) {
    return { name: 'projects' }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'projects' }
  }
})

export default router
