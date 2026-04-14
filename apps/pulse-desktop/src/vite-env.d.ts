/// <reference types="vite/client" />

interface AppWindowApi {
  minimize: () => Promise<void>
  toggleMaximize: () => Promise<boolean>
  close: () => Promise<void>
  isMaximized: () => Promise<boolean>
  isDevtoolsOpened: () => Promise<boolean>
  isFocused: () => Promise<boolean>
  onStateChanged: (callback: (payload: { isMaximized: boolean }) => void) => () => void
  onDevtoolsVisibilityChanged: (callback: (payload: { isOpen: boolean }) => void) => () => void
}

declare global {
  interface Window {
    appWindow?: AppWindowApi
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
