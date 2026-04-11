import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import type { ToastItem, ToastType } from '../types/ui'

const THEME_KEY = 'pulse:themeDark'

function applyHtmlDarkClass(isDark: boolean) {
  document.documentElement.classList.toggle('dark', isDark)
}

export const useUiStore = defineStore('ui', () => {
  const isDark = ref(false)
  const isOffline = ref(false)
  const ptrRefreshing = ref(false)
  const toasts = ref<ToastItem[]>([])

  function initTheme() {
    const stored = localStorage.getItem(THEME_KEY)
    if (stored === '1') {
      isDark.value = true
    } else if (stored === '0') {
      isDark.value = false
    }
    applyHtmlDarkClass(isDark.value)
  }

  function setDark(value: boolean) {
    isDark.value = value
    localStorage.setItem(THEME_KEY, value ? '1' : '0')
    applyHtmlDarkClass(value)
  }

  function toggleTheme() {
    setDark(!isDark.value)
  }

  watch(isDark, (v) => applyHtmlDarkClass(v), { flush: 'post' })

  function pushToast(message: string, type: ToastType = 'info') {
    const id = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
    toasts.value = [...toasts.value, { id, message, type }]
    window.setTimeout(() => {
      toasts.value = toasts.value.filter((t) => t.id !== id)
    }, 2500)
  }

  function setOffline(value: boolean) {
    isOffline.value = value
  }

  function setPtrRefreshing(value: boolean) {
    ptrRefreshing.value = value
  }

  function bindWindowOnlineStatus() {
    const onOnline = () => {
      setOffline(false)
      pushToast('Подключение восстановлено', 'success')
    }
    const onOffline = () => {
      setOffline(true)
    }
    window.addEventListener('online', onOnline)
    window.addEventListener('offline', onOffline)
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
      setOffline(true)
    }
    return () => {
      window.removeEventListener('online', onOnline)
      window.removeEventListener('offline', onOffline)
    }
  }

  return {
    isDark,
    isOffline,
    ptrRefreshing,
    toasts,
    initTheme,
    setDark,
    toggleTheme,
    pushToast,
    setOffline,
    setPtrRefreshing,
    bindWindowOnlineStatus,
  }
})
