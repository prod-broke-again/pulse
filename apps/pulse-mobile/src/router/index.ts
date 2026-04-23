import { createRouter, createWebHistory } from 'vue-router'
import AuthCallbackPage from '../pages/AuthCallbackPage.vue'
import ChatPage from '../pages/ChatPage.vue'
import ClientHistoryPage from '../pages/ClientHistoryPage.vue'
import HistoryPage from '../pages/HistoryPage.vue'
import InboxPage from '../pages/InboxPage.vue'
import LoginPage from '../pages/LoginPage.vue'
import SettingsPage from '../pages/SettingsPage.vue'
import SettingsQuickLinksPage from '../pages/SettingsQuickLinksPage.vue'
import SettingsTemplatesPage from '../pages/SettingsTemplatesPage.vue'
import { useAuthStore } from '../stores/authStore'

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
      path: '/settings/templates',
      name: 'settings-templates',
      component: SettingsTemplatesPage,
    },
    {
      path: '/settings/quick-links',
      name: 'settings-quick-links',
      component: SettingsQuickLinksPage,
    },
    {
      path: '/chat/:id',
      name: 'chat',
      component: ChatPage,
    },
    {
      path: '/history/client/:externalUserId',
      name: 'client-history',
      component: ClientHistoryPage,
    },
  ],
})

/**
 * All non-`public` routes need a token unless explicitly disabled.
 * Set `VITE_REQUIRE_AUTH=false` in `.env` for local dev without API login.
 */
const skipAuthGuard = import.meta.env.VITE_REQUIRE_AUTH === 'false'

router.beforeEach((to) => {
  if (skipAuthGuard || to.meta.public) {
    return true
  }
  const auth = useAuthStore()
  if (!auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  return true
})

export default router
