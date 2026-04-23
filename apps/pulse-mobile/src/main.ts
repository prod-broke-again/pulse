import { App as CapacitorApp } from '@capacitor/app'
import { Browser } from '@capacitor/browser'
import { Capacitor } from '@capacitor/core'
import { createApp } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'
import { initializeCapacitor } from './lib/capacitor'
import { nudgeModeratorPresenceOnForeground } from './lib/moderatorPresenceMobile'
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
    nudgeModeratorPresenceOnForeground()
    void useInboxStore().loadInbox()
    const chat = useChatStore()
    if (chat.activeChatId) {
      void chat.fetchThread(chat.activeChatId, { force: true })
    }
  },
})

setupPushNotificationDeepLinks(router)

/**
 * Native OAuth return via pulseapp://auth/callback?...
 * - appUrlOpen: works when the app is already running (in-app browser return).
 * - getLaunchUrl after mount: cold start — the OS often fires appUrlOpen before
 *   this JS module registers a listener, so the callback was previously lost
 *   until the user tapped "Войти" again.
 */
let consumeNativeOAuthLaunchUrl: (() => Promise<void>) | null = null

if (Capacitor.isNativePlatform()) {
  const seenOAuth = new Set<string>()

  const handleOAuthReturn = async (url: string) => {
    if (!url.includes('callback')) {
      return
    }
    const p = parseOAuthCallbackParams(url)
    if (p.code && p.state) {
      const dedupe = `c:${p.code}`
      if (seenOAuth.has(dedupe)) {
        return
      }
      seenOAuth.add(dedupe)
      await Browser.close().catch(() => {})
      await router.replace({
        name: 'auth-callback',
        query: { code: p.code, state: p.state },
      })
    } else if (p.error) {
      const dedupe = `e:${p.error}:${p.error_description ?? ''}`
      if (seenOAuth.has(dedupe)) {
        return
      }
      seenOAuth.add(dedupe)
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

  consumeNativeOAuthLaunchUrl = async () => {
    await router.isReady()
    const launch = await CapacitorApp.getLaunchUrl()
    if (launch?.url) {
      await handleOAuthReturn(launch.url)
    }
  }
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

if (consumeNativeOAuthLaunchUrl) {
  void consumeNativeOAuthLaunchUrl()
}

/** Ensure inbox realtime (moderator channel) subscribes even before first inbox visit. */
useInboxStore()
