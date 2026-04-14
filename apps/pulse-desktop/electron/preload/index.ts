import { ipcRenderer, contextBridge } from 'electron'

// --------- Expose some API to the Renderer process ---------
contextBridge.exposeInMainWorld('ipcRenderer', {
  on(...args: Parameters<typeof ipcRenderer.on>) {
    const [channel, listener] = args
    return ipcRenderer.on(channel, (event, ...args) => listener(event, ...args))
  },
  off(...args: Parameters<typeof ipcRenderer.off>) {
    const [channel, ...omit] = args
    return ipcRenderer.off(channel, ...omit)
  },
  send(...args: Parameters<typeof ipcRenderer.send>) {
    const [channel, ...omit] = args
    return ipcRenderer.send(channel, ...omit)
  },
  invoke(...args: Parameters<typeof ipcRenderer.invoke>) {
    const [channel, ...omit] = args
    return ipcRenderer.invoke(channel, ...omit)
  },

  // You can expose other APTs you need here.
  // ...
})

contextBridge.exposeInMainWorld('appWindow', {
  minimize: () => ipcRenderer.invoke('window:minimize'),
  toggleMaximize: () => ipcRenderer.invoke('window:toggle-maximize') as Promise<boolean>,
  close: () => ipcRenderer.invoke('window:close'),
  isMaximized: () => ipcRenderer.invoke('window:is-maximized') as Promise<boolean>,
  isDevtoolsOpened: () => ipcRenderer.invoke('window:is-devtools-opened') as Promise<boolean>,
  isFocused: () => ipcRenderer.invoke('window:is-focused') as Promise<boolean>,
  onStateChanged: (callback: (payload: { isMaximized: boolean }) => void) => {
    const listener = (_event: Electron.IpcRendererEvent, payload: { isMaximized: boolean }) => callback(payload)
    ipcRenderer.on('window-state-changed', listener)
    return () => ipcRenderer.removeListener('window-state-changed', listener)
  },
  onDevtoolsVisibilityChanged: (callback: (payload: { isOpen: boolean }) => void) => {
    const listener = (_event: Electron.IpcRendererEvent, payload: { isOpen: boolean }) => callback(payload)
    ipcRenderer.on('window-devtools-visibility-changed', listener)
    return () => ipcRenderer.removeListener('window-devtools-visibility-changed', listener)
  },
})

contextBridge.exposeInMainWorld('electronShell', {
  openExternal: (url: string) => ipcRenderer.invoke('shell:open-external', url) as Promise<void>,
})

contextBridge.exposeInMainWorld('electronOAuth', {
  onCallback: (listener: (url: string) => void) => {
    const handler = (_event: Electron.IpcRendererEvent, oauthUrl: string) => listener(oauthUrl)
    ipcRenderer.on('oauth-callback', handler)
    return () => ipcRenderer.removeListener('oauth-callback', handler)
  },
})

/** Локальный кэш истории (SQLite в main) + очередь исходящих при офлайне. */
contextBridge.exposeInMainWorld('pulseLocalCache', {
  get: (chatId: number) => ipcRenderer.invoke('local:getMessageCache', chatId) as Promise<string | null>,
  set: (chatId: number, json: string) => ipcRenderer.invoke('local:setMessageCache', chatId, json) as Promise<void>,
})

contextBridge.exposeInMainWorld('pulseOutbox', {
  list: () => ipcRenderer.invoke('outbox:list') as Promise<
    Array<{ id: string; chat_id: number; payload: string; created_at: number; attempts: number }>
  >,
  add: (id: string, chatId: number, payload: string) =>
    ipcRenderer.invoke('outbox:add', id, chatId, payload) as Promise<void>,
  remove: (id: string) => ipcRenderer.invoke('outbox:remove', id) as Promise<void>,
  incrementAttempts: (id: string) => ipcRenderer.invoke('outbox:incrementAttempts', id) as Promise<void>,
})
