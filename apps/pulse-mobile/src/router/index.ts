import { createRouter, createWebHistory } from 'vue-router'
import AuthCallbackPage from '../pages/AuthCallbackPage.vue'
import ChatPage from '../pages/ChatPage.vue'
import HistoryPage from '../pages/HistoryPage.vue'
import InboxPage from '../pages/InboxPage.vue'
import LoginPage from '../pages/LoginPage.vue'
import SettingsPage from '../pages/SettingsPage.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginPage,
      meta: { public: true },
    },
    {
      path: '/auth/callback',
      name: 'auth-callback',
      component: AuthCallbackPage,
      meta: { public: true },
    },
    {
      path: '/',
      name: 'inbox',
      component: InboxPage,
    },
    {
      path: '/history',
      name: 'history',
      component: HistoryPage,
    },
    {
      path: '/settings',
      name: 'settings',
      component: SettingsPage,
    },
    {
      path: '/chat/:id',
      name: 'chat',
      component: ChatPage,
    },
  ],
})

const requireAuth = import.meta.env.VITE_REQUIRE_AUTH === 'true'

router.beforeEach((to) => {
  if (!requireAuth || to.meta.public) {
    return true
  }
  const hasToken =
    typeof localStorage !== 'undefined' && Boolean(localStorage.getItem('api-token'))
  if (!hasToken && to.name !== 'login') {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  return true
})

export default router
