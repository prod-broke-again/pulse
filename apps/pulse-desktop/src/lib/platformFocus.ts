/** True when the Pulse window is focused (Electron) or the browser tab is focused. */
export async function isAppWindowFocused(): Promise<boolean> {
  if (typeof window === 'undefined') {
    return false
  }
  const w = window as Window & {
    appWindow?: { isFocused?: () => Promise<boolean> }
  }
  if (w.appWindow?.isFocused) {
    try {
      return await w.appWindow.isFocused()
    } catch {
      return document.hasFocus()
    }
  }
  return document.visibilityState === 'visible' && document.hasFocus()
}
