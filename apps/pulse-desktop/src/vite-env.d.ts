/// <reference types="vite/client" />

type CloseButtonBehavior = 'ask' | 'quit' | 'hide-to-tray'

interface AppWindowApi {
  getVersion: () => Promise<string>
  minimize: () => Promise<void>
  toggleMaximize: () => Promise<boolean>
  requestClose: () => Promise<void>
  isMaximized: () => Promise<boolean>
  isDevtoolsOpened: () => Promise<boolean>
  isFocused: () => Promise<boolean>
  onStateChanged: (callback: (payload: { isMaximized: boolean }) => void) => () => void
  onDevtoolsVisibilityChanged: (callback: (payload: { isOpen: boolean }) => void) => () => void
}

interface PulseWindowSettingsApi {
  getPrefs: () => Promise<{ closeButtonBehavior: CloseButtonBehavior }>
  setPrefs: (p: { closeButtonBehavior: CloseButtonBehavior }) => Promise<void>
  onCloseRequested: (cb: () => void) => () => void
  confirmClose: (opts: { action: 'quit' | 'hide-to-tray'; remember: boolean }) => Promise<void>
}

interface DesktopUpdaterApi {
  checkNow: () => Promise<{ ok: true } | { ok: false; reason: string }>
  installNow: () => Promise<void>
  onStatus: (listener: (payload: {
    level: 'info' | 'ready' | 'error'
    code: 'checking' | 'available' | 'not-available' | 'downloaded' | 'error'
    message: string
    version?: string
    releaseNotes?: string
  }) => void) => () => void
}

declare global {
  interface Window {
    appWindow?: AppWindowApi
    pulseWindowSettings?: PulseWindowSettingsApi
    desktopUpdater?: DesktopUpdaterApi
    electronOAuth?: {
      onCallback: (listener: (url: string) => void) => () => void
    }
    electronShell?: {
      openExternal: (url: string) => Promise<void>
    }
    pulseLocalCache?: {
      get: (chatId: number) => Promise<string | null>
      set: (chatId: number, json: string) => Promise<void>
    }
    pulseOutbox?: {
      list: () => Promise<Array<{ id: string; chat_id: number; payload: string; created_at: number; attempts: number }>>
      add: (id: string, chatId: number, payload: string) => Promise<void>
      remove: (id: string) => Promise<void>
      incrementAttempts: (id: string) => Promise<void>
    }
  }
}

export {}
