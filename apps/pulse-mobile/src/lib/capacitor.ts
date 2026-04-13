import { Capacitor } from '@capacitor/core'
import { App as CapacitorApp } from '@capacitor/app'
import { Keyboard, KeyboardResize } from '@capacitor/keyboard'
import { Network } from '@capacitor/network'
import { SplashScreen } from '@capacitor/splash-screen'
import { StatusBar, Style as StatusBarStyle } from '@capacitor/status-bar'

export type CapacitorLifecycleHooks = {
  /** Приложение снова на переднем плане — обновить список чатов и активный тред. */
  onAppBecameActive?: () => void
  /** Сеть восстановилась / изменилась (опционально refresh). */
  onNetworkStatusChange?: () => void
}

export async function initializeCapacitor(hooks?: CapacitorLifecycleHooks): Promise<void> {
  if (!Capacitor.isNativePlatform()) return

  await SplashScreen.hide().catch(() => {})
  await StatusBar.setStyle({ style: StatusBarStyle.Dark }).catch(() => {})
  await Keyboard.setResizeMode({ mode: KeyboardResize.Body }).catch(() => {})

  Network.addListener('networkStatusChange', () => {
    hooks?.onNetworkStatusChange?.()
  }).catch(() => {})
  CapacitorApp.addListener('appStateChange', ({ isActive }) => {
    if (isActive) {
      hooks?.onAppBecameActive?.()
    }
  }).catch(() => {})
}
