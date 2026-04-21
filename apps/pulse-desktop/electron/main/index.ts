import { app, BrowserWindow, shell, ipcMain, Tray, Menu, nativeImage } from 'electron'
import electronUpdater from 'electron-updater'
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
import {
  loadWindowPreferences,
  saveWindowPreferences,
  type CloseButtonBehavior,
} from './windowPreferences'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const { autoUpdater } = electronUpdater

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

/** GitHub Releases в публичном репо (см. electron-builder.json5 → publish). */
function setupAutoUpdate(): void {
  if (!app.isPackaged) {
    return
  }
  autoUpdater.autoDownload = true
  autoUpdater.autoInstallOnAppQuit = true
  void autoUpdater.checkForUpdatesAndNotify().catch(() => {})
}

const argvOAuthUrl = process.argv.find((arg) => arg.startsWith(`${OAUTH_PROTOCOL}:`))
if (argvOAuthUrl) {
  pendingOAuthUrl = argvOAuthUrl
}

if (!app.requestSingleInstanceLock()) {
  app.quit()
  process.exit(0)
}

let win: BrowserWindow | null = null
let tray: Tray | null = null
/** True when user chose «Выйти» / из меню трея — иначе закрытие окна сворачивает в трей. */
let isAppQuitting = false

let closeButtonBehavior: CloseButtonBehavior = 'ask'

const preload = path.join(__dirname, '../preload/index.mjs')
const indexHtml = path.join(RENDERER_DIST, 'index.html')

function trayIcon(): Electron.NativeImage {
  const iconPath = path.join(process.env.VITE_PUBLIC ?? '', 'logo.png')
  let image = nativeImage.createFromPath(iconPath)
  if (!image.isEmpty() && process.platform === 'win32') {
    image = image.resize({ width: 16, height: 16 })
  }
  return image
}

function ensureTray(): void {
  if (tray) {
    return
  }
  const image = trayIcon()
  if (image.isEmpty()) {
    return
  }
  tray = new Tray(image)
  tray.setToolTip('Pulse — АЧПП')
  const show = (): void => {
    if (win && !win.isDestroyed()) {
      win.show()
      win.focus()
    }
  }
  tray.setContextMenu(
    Menu.buildFromTemplate([
      { label: 'Открыть Pulse', click: show },
      { type: 'separator' },
      {
        label: 'Выйти полностью',
        click: (): void => {
          isAppQuitting = true
          app.quit()
        },
      },
    ]),
  )
  tray.on('click', show)
}

function destroyTray(): void {
  tray?.destroy()
  tray = null
}

function attachWindowCloseHandler(browser: BrowserWindow): void {
  browser.on('close', (e) => {
    if (isAppQuitting) {
      return
    }
    e.preventDefault()
    if (closeButtonBehavior === 'quit') {
      isAppQuitting = true
      app.quit()
      return
    }
    if (closeButtonBehavior === 'hide-to-tray') {
      browser.hide()
      ensureTray()
      return
    }
    browser.webContents.send('app:close-requested')
  })
}

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
  attachWindowCloseHandler(win)
  // win.webContents.on('will-navigate', (event, url) => { }) #344
}

if (process.platform === 'darwin') {
  app.on('open-url', (event, url) => {
    event.preventDefault()
    dispatchOAuthUrlToRenderer(url)
  })
}

function registerWindowManagementIpc(): void {
  ipcMain.handle('windowPrefs:get', (): { closeButtonBehavior: CloseButtonBehavior } => ({
    closeButtonBehavior,
  }))
  ipcMain.handle('windowPrefs:set', (_, p: { closeButtonBehavior: CloseButtonBehavior }) => {
    if (
      p.closeButtonBehavior !== 'ask'
      && p.closeButtonBehavior !== 'quit'
      && p.closeButtonBehavior !== 'hide-to-tray'
    ) {
      return
    }
    closeButtonBehavior = p.closeButtonBehavior
    saveWindowPreferences({ closeButtonBehavior: p.closeButtonBehavior })
  })
  /** Крестик в заголовке: инициирует обработку «спросить / трей / выход». */
  ipcMain.handle('window:request-user-close', () => {
    win?.close()
  })
  ipcMain.handle(
    'window:confirm-close',
    (
      _,
      opts: {
        action: 'quit' | 'hide-to-tray'
        remember: boolean
      },
    ) => {
      if (opts.remember) {
        const next: CloseButtonBehavior = opts.action === 'quit' ? 'quit' : 'hide-to-tray'
        closeButtonBehavior = next
        saveWindowPreferences({ closeButtonBehavior: next })
      }
      if (opts.action === 'quit') {
        isAppQuitting = true
        app.quit()
      } else {
        win?.hide()
        ensureTray()
      }
    },
  )
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
  closeButtonBehavior = loadWindowPreferences().closeButtonBehavior
  await initLocalDb()
  registerWindowManagementIpc()
  registerLocalStoreIpc()
  setupAutoUpdate()
  await createWindow()
})

app.on('before-quit', () => {
  destroyTray()
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
    if (!win.isVisible()) {
      win.show()
    }
    if (win.isMinimized()) {
      win.restore()
    }
    win.focus()
  }
})

app.on('activate', () => {
  const allWindows = BrowserWindow.getAllWindows()
  if (allWindows.length) {
    const w = allWindows[0]!
    if (!w.isDestroyed() && !w.isVisible()) {
      w.show()
    }
    w.focus()
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
