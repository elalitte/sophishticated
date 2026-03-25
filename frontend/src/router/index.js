import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/auth/Login.vue'),
    meta: { public: true }
  },
  {
    path: '/change-password',
    name: 'ChangePassword',
    component: () => import('../views/auth/ChangePassword.vue')
  },
  {
    path: '/',
    redirect: '/dashboard'
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('../views/Dashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/recipients',
    name: 'RecipientList',
    component: () => import('../views/recipients/RecipientList.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/recipients/groups',
    name: 'GroupManager',
    component: () => import('../views/recipients/GroupManager.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/recipients/:id',
    name: 'RecipientDetail',
    component: () => import('../views/recipients/RecipientDetail.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/templates',
    name: 'TemplateList',
    component: () => import('../views/templates/TemplateList.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/templates/create',
    name: 'TemplateCreate',
    component: () => import('../views/templates/EmailEditor.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/templates/:id/edit',
    name: 'TemplateEdit',
    component: () => import('../views/templates/EmailEditor.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/templates/packs',
    name: 'PackLibrary',
    component: () => import('../views/templates/PackLibrary.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/templates/pages',
    name: 'PageList',
    component: () => import('../views/templates/PageList.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/templates/pages/create',
    name: 'PageCreate',
    component: () => import('../views/templates/PageEditor.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/templates/pages/:id/edit',
    name: 'PageEdit',
    component: () => import('../views/templates/PageEditor.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/templates/preview',
    name: 'Preview',
    component: () => import('../views/templates/Preview.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/campaigns',
    name: 'CampaignList',
    component: () => import('../views/campaigns/CampaignList.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/campaigns/create',
    name: 'CampaignCreate',
    component: () => import('../views/campaigns/CampaignCreate.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/campaigns/:id/edit',
    name: 'CampaignEdit',
    component: () => import('../views/campaigns/CampaignCreate.vue'),
    meta: { requiresAuth: true },
    props: true
  },
  {
    path: '/campaigns/:id/monitor',
    name: 'CampaignMonitor',
    component: () => import('../views/campaigns/CampaignMonitor.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/stats',
    name: 'StatsDashboard',
    component: () => import('../views/stats/StatsDashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/stats/campaign/:id',
    name: 'StatsCampaign',
    component: () => import('../views/stats/StatsCampaign.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/stats/group/:id',
    name: 'StatsGroup',
    component: () => import('../views/stats/StatsGroup.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/admin/users',
    name: 'UserManager',
    component: () => import('../views/admin/UserManager.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

const publicRoutes = ['Login']
let sessionChecked = false

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // On first navigation (page load/refresh), check session with backend
  if (!sessionChecked) {
    sessionChecked = true
    await authStore.fetchUser()
  }

  if (to.meta.public || publicRoutes.includes(to.name)) {
    // If already logged in and going to login page, redirect to dashboard
    if (to.name === 'Login' && authStore.isAuthenticated) {
      next({ name: 'Dashboard' })
      return
    }
    next()
    return
  }

  if (!authStore.isAuthenticated) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
    return
  }

  if (to.meta.requiresAdmin && authStore.user?.role !== 'admin') {
    next({ name: 'Dashboard' })
    return
  }

  next()
})

export default router
