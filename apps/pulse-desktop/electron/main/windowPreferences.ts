import fs from 'node:fs'
import path from 'node:path'
import { app } from 'electron'

/** Поведение кнопки закрытия (локально на устройстве). */
export type CloseButtonBehavior = 'ask' | 'quit' | 'hide-to-tray'

export interface WindowPreferences {
  closeButtonBehavior: CloseButtonBehavior
}

const defaultPreferences: WindowPreferences = {
  closeButtonBehavior: 'ask',
}

function prefsPath(): string {
  return path.join(app.getPath('userData'), 'window-preferences.json')
}

export function loadWindowPreferences(): WindowPreferences {
  try {
    const raw = fs.readFileSync(prefsPath(), 'utf8')
    const data = JSON.parse(raw) as Partial<WindowPreferences>
    if (data.closeButtonBehavior !== 'ask' && data.closeButtonBehavior !== 'quit' && data.closeButtonBehavior !== 'hide-to-tray') {
      return { ...defaultPreferences }
    }
    return { closeButtonBehavior: data.closeButtonBehavior }
  } catch {
    return { ...defaultPreferences }
  }
}

export function saveWindowPreferences(next: WindowPreferences): void {
  try {
    fs.mkdirSync(path.dirname(prefsPath()), { recursive: true })
    fs.writeFileSync(prefsPath(), JSON.stringify(next, null, 2), 'utf8')
  } catch {
    /* ignore disk errors */
  }
}
