import { App as CapacitorApp } from '@capacitor/app'
import { Browser } from '@capacitor/browser'
import { Capacitor } from '@capacitor/core'
import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { initializeCapacitor } from './lib/capacitor'
import { parseOAuthCallbackParams } from './lib/oauthUrl'
import { setupPushNotificationDeepLinks } from './lib/pushDevice'
import { useChatStore } from './stores/chatStore'
import { useInboxStore } from './stores/inboxStore'
import { useUiStore } from './stores/uiStore'

const app = createApp(App)
const pinia = createPinia()
setActivePinia(pinia)
app.use(pinia)
app.use(router)

initializeCapacitor({
  onAppBecameActive: () => {
    void useInboxStore().loadInbox()
    const chat = useChatStore()
    if (chat.activeChatId) {
      void chat.fetchThread(chat.activeChatId)
    }
  },
})

setupPushNotificationDeepLinks(router)

/** Native: return from IdP via pulseapp://auth/callback?... */
if (Capacitor.isNativePlatform()) {
  const handleOAuthReturn = async (url: string) => {
    if (!url.includes('callback')) {
      return
    }
    const p = parseOAuthCallbackParams(url)
    if (p.code && p.state) {
      await Browser.close().catch(() => {})
      await router.replace({
        name: 'auth-callback',
        query: { code: p.code, state: p.state },
      })
    } else if (p.error) {
      await Browser.close().catch(() => {})
      await router.replace({
        name: 'auth-callback',
        query: {
          error: p.error,
          ...(p.error_description ? { error_description: p.error_description } : {}),
        },
      })
    }
  }

  void CapacitorApp.addListener('appUrlOpen', ({ url }) => {
    void handleOAuthReturn(url)
  })
}

const ui = useUiStore()
ui.initTheme()
ui.bindWindowOnlineStatus()

if (typeof window !== 'undefined' && window.visualViewport) {
  window.visualViewport.addEventListener('resize', () => {
    document.documentElement.style.setProperty(
      '--keyboard-height',
      `${window.innerHeight - window.visualViewport!.height}px`,
    )
  })
}

app.mount('#app')
