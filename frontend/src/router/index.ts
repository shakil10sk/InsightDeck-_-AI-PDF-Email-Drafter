import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes: RouteRecordRaw[] = [
  { path: '/login', name: 'login', component: () => import('@/pages/Login.vue'), meta: { guest: true } },
  { path: '/register', name: 'register', component: () => import('@/pages/Register.vue'), meta: { guest: true } },
  { path: '/forgot-password', name: 'forgot', component: () => import('@/pages/ForgotPassword.vue'), meta: { guest: true } },
  { path: '/reset-password', name: 'reset', component: () => import('@/pages/ResetPassword.vue'), meta: { guest: true } },
  {
    path: '/',
    component: () => import('@/layouts/AppLayout.vue'),
    meta: { auth: true },
    children: [
      { path: '', name: 'dashboard', component: () => import('@/pages/Dashboard.vue') },
      { path: 'documents', name: 'documents', component: () => import('@/pages/Documents.vue') },
      { path: 'documents/:id', name: 'document.show', component: () => import('@/pages/DocumentDetail.vue'), props: true },
      { path: 'chat', name: 'chat', component: () => import('@/pages/Chat.vue') },
      { path: 'chat/:id', name: 'chat.show', component: () => import('@/pages/ChatDetail.vue'), props: true },
      { path: 'drafts', name: 'drafts', component: () => import('@/pages/Drafts.vue') },
      { path: 'drafts/:id', name: 'drafts.show', component: () => import('@/pages/DraftDetail.vue'), props: true },
      { path: 'settings', name: 'settings', component: () => import('@/pages/Settings.vue') },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.initialized) {
    await auth.fetchMe()
  }
  if (to.meta.auth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})
