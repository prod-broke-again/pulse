import { app, BrowserWindow, shell, ipcMain } from 'electron'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import os from 'node:os'
import {
  closeLocalDb,
  getMessageCacheJson,
  initLocalDb,
  outboxAdd,
  outboxIncrementAttempts,
  outboxList,
  outboxRemove,
  setMessageCacheJson,
} from './localDb'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

/** Deep link for ACHPP ID OAuth callback (must match IdP client redirect URI). */
const OAUTH_PROTOCOL = 'pulse-desktop'

let pendingOAuthUrl: string | null = null

// The built directory structure
//
// ├─┬ dist-electron
// │ ├─┬ main
// │ │ └── index.js    > Electron-Main
// │ └─┬ preload
// │   └── index.mjs   > Preload-Scripts
// ├─┬ dist
// │ └── index.html    > Electron-Renderer
//
process.env.APP_ROOT = path.join(__dirname, '../..')

export const MAIN_DIST = path.join(process.env.APP_ROOT, 'dist-electron')
export const RENDERER_DIST = path.join(process.env.APP_ROOT, 'dist')
export const VITE_DEV_SERVER_URL = process.env.VITE_DEV_SERVER_URL

process.env.VITE_PUBLIC = VITE_DEV_SERVER_URL
  ? path.join(process.env.APP_ROOT, 'public')
  : RENDERER_DIST

// Disable GPU Acceleration for Windows 7
if (os.release().startsWith('6.1')) app.disableHardwareAcceleration()

// Windows: идентификатор для уведомлений и закрепления (совпадает с appId в electron-builder)
if (process.platform === 'win32') {
  app.setAppUserModelId('com.achpp.pulse.desktop')
}

/** Register custom protocol handler for OAuth redirect (pulse-desktop://auth/callback?...). */
function registerOAuthProtocol(): void {
  if (process.defaultApp) {
    if (process.argv.length >= 2) {
      app.setAsDefaultProtocolClient(OAUTH_PROTOCOL, process.execPath, [path.resolve(process.argv[1]!)])
    }
  } else {
    app.setAsDefaultProtocolClient(OAUTH_PROTOCOL)
  }
}

registerOAuthProtocol()

const argvOAuthUrl = process.argv.find((arg) => arg.startsWith(`${OAUTH_PROTOCOL}:`))
if (argvOAuthUrl) {
  pendingOAuthUrl = argvOAuthUrl
}

if (!app.requestSingleInstanceLock()) {
  app.quit()
  process.exit(0)
}

let win: BrowserWindow | null = null
const preload = path.join(__dirname, '../preload/index.mjs')
const indexHtml = path.join(RENDERER_DIST, 'index.html')

function dispatchOAuthUrlToRenderer(url: string): void {
  if (!url.startsWith(`${OAUTH_PROTOCOL}:`)) {
    return
  }
  if (win && !win.isDestroyed()) {
    win.webContents.send('oauth-callback', url)
    if (win.isMinimized()) {
      win.restore()
    }
    win.focus()
  } else {
    pendingOAuthUrl = url
  }
}

async function createWindow() {
  win = new BrowserWindow({
    title: 'Pulse — АЧПП',
    icon: path.join(process.env.VITE_PUBLIC, 'logo.png'),
    frame: false,
    titleBarStyle: 'hidden',
    minWidth: 1180,
    minHeight: 760,
    webPreferences: {
      preload,
      // Warning: Enable nodeIntegration and disable contextIsolation is not secure in production
      // nodeIntegration: true,

      // Consider using contextBridge.exposeInMainWorld
      // Read more on https://www.electronjs.org/docs/latest/tutorial/context-isolation
      // contextIsolation: false,
    },
  })

  if (VITE_DEV_SERVER_URL) { // #298
    win.loadURL(VITE_DEV_SERVER_URL)
    // Open devTool if the app is not packaged
    win.webContents.openDevTools()
  } else {
    win.loadFile(indexHtml)
  }

  // Test actively push message to the Electron-Renderer
  win.webContents.on('did-finish-load', () => {
    win?.webContents.send('main-process-message', new Date().toLocaleString())
    if (pendingOAuthUrl) {
      const url = pendingOAuthUrl
      pendingOAuthUrl = null
      win?.webContents.send('oauth-callback', url)
    }
  })
  win.on('maximize', () => {
    win?.webContents.send('window-state-changed', { isMaximized: true })
  })
  win.on('unmaximize', () => {
    win?.webContents.send('window-state-changed', { isMaximized: false })
  })
  win.webContents.on('devtools-opened', () => {
    win?.webContents.send('window-devtools-visibility-changed', { isOpen: true })
  })
  win.webContents.on('devtools-closed', () => {
    win?.webContents.send('window-devtools-visibility-changed', { isOpen: false })
  })

  // Make all links open with the browser, not with the application
  win.webContents.setWindowOpenHandler(({ url }) => {
    if (url.startsWith('https:')) shell.openExternal(url)
    return { action: 'deny' }
  })
  // win.webContents.on('will-navigate', (event, url) => { }) #344
}

if (process.platform === 'darwin') {
  app.on('open-url', (event, url) => {
    event.preventDefault()
    dispatchOAuthUrlToRenderer(url)
  })
}

function registerLocalStoreIpc(): void {
  ipcMain.handle('local:getMessageCache', (_, chatId: number) => getMessageCacheJson(chatId))
  ipcMain.handle('local:setMessageCache', (_, chatId: number, json: string) => {
    setMessageCacheJson(chatId, json)
  })
  ipcMain.handle('outbox:list', () => outboxList())
  ipcMain.handle('outbox:add', (_, id: string, chatId: number, payload: string) => {
    outboxAdd(id, chatId, payload)
  })
  ipcMain.handle('outbox:remove', (_, id: string) => {
    outboxRemove(id)
  })
  ipcMain.handle('outbox:incrementAttempts', (_, id: string) => {
    outboxIncrementAttempts(id)
  })
}

app.whenReady().then(async () => {
  await initLocalDb()
  registerLocalStoreIpc()
  await createWindow()
})

app.on('before-quit', () => {
  closeLocalDb()
})

app.on('window-all-closed', () => {
  win = null
  if (process.platform !== 'darwin') app.quit()
})

app.on('second-instance', (_event, commandLine) => {
  const url = commandLine.find((arg) => arg.startsWith(`${OAUTH_PROTOCOL}:`))
  if (url) {
    dispatchOAuthUrlToRenderer(url)
  }
  if (win) {
    if (win.isMinimized()) {
      win.restore()
    }
    win.focus()
  }
})

app.on('activate', () => {
  const allWindows = BrowserWindow.getAllWindows()
  if (allWindows.length) {
    allWindows[0].focus()
  } else {
    createWindow()
  }
})

// New window example arg: new windows url
ipcMain.handle('open-win', (_, arg) => {
  const childWindow = new BrowserWindow({
    webPreferences: {
      preload,
      nodeIntegration: true,
      contextIsolation: false,
    },
  })

  if (VITE_DEV_SERVER_URL) {
    childWindow.loadURL(`${VITE_DEV_SERVER_URL}#${arg}`)
  } else {
    childWindow.loadFile(indexHtml, { hash: arg })
  }
})

ipcMain.handle('window:minimize', () => {
  win?.minimize()
})

ipcMain.handle('window:toggle-maximize', () => {
  if (!win) return false

  if (win.isMaximized()) {
    win.unmaximize()
    return false
  }

  win.maximize()
  return true
})

ipcMain.handle('window:close', () => {
  win?.close()
})

ipcMain.handle('window:is-maximized', () => {
  return win?.isMaximized() ?? false
})

ipcMain.handle('window:is-devtools-opened', () => {
  return win?.webContents.isDevToolsOpened() ?? false
})

ipcMain.handle('window:is-focused', () => {
  return win?.isFocused() ?? false
})

ipcMain.handle('shell:open-external', async (_, url: string) => {
  await shell.openExternal(url)
})
