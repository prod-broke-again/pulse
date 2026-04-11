import { Capacitor } from '@capacitor/core'
import { App as CapacitorApp } from '@capacitor/app'
import { Keyboard, KeyboardResize } from '@capacitor/keyboard'
import { Network } from '@capacitor/network'
import { SplashScreen } from '@capacitor/splash-screen'
import { StatusBar, Style as StatusBarStyle } from '@capacitor/status-bar'

export async function initializeCapacitor(): Promise<void> {
  if (!Capacitor.isNativePlatform()) return

  await SplashScreen.hide().catch(() => {})
  await StatusBar.setStyle({ style: StatusBarStyle.Dark }).catch(() => {})
  await Keyboard.setResizeMode({ mode: KeyboardResize.Body }).catch(() => {})

  Network.addListener('networkStatusChange', () => {}).catch(() => {})
  CapacitorApp.addListener('appStateChange', () => {}).catch(() => {})
}
