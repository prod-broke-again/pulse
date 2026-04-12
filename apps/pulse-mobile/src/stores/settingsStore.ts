import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { registerPushWithBackend, unregisterPushFromBackend } from '../lib/pushDevice'
import { useUiStore } from './uiStore'

const SETTINGS_KEY = 'pulse:settingsV1'

type SettingsState = {
  push: boolean
  sound: boolean
  vibration: boolean
  aiHints: boolean
  aiSummary: boolean
}

const defaults: SettingsState = {
  push: true,
  sound: true,
  vibration: false,
  aiHints: true,
  aiSummary: true,
}

function loadFromStorage(): SettingsState {
  if (typeof localStorage === 'undefined') return { ...defaults }
  try {
    const raw = localStorage.getItem(SETTINGS_KEY)
    if (!raw) return { ...defaults }
    return { ...defaults, ...JSON.parse(raw) }
  } catch {
    return { ...defaults }
  }
}

function saveToStorage(s: SettingsState) {
  if (typeof localStorage === 'undefined') return
  try {
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(s))
  } catch {
    /* ignore */
  }
}

export const useSettingsStore = defineStore('settings', () => {
  const initial = loadFromStorage()

  const push = ref(initial.push)
  const sound = ref(initial.sound)
  const vibration = ref(initial.vibration)
  const aiHints = ref(initial.aiHints)
  const aiSummary = ref(initial.aiSummary)

  const pushSyncing = ref(false)

  function persist() {
    saveToStorage({
      push: push.value,
      sound: sound.value,
      vibration: vibration.value,
      aiHints: aiHints.value,
      aiSummary: aiSummary.value,
    })
  }

  watch([sound, vibration, aiHints, aiSummary], () => {
    persist()
  })

  async function setPush(next: boolean) {
    const ui = useUiStore()
    if (next) {
      pushSyncing.value = true
      try {
        await registerPushWithBackend()
        push.value = true
        persist()
        ui.pushToast('Push-уведомления включены', 'success')
      } catch {
        push.value = false
        persist()
        ui.pushToast('Не удалось включить push (проверьте разрешения)', 'error')
      } finally {
        pushSyncing.value = false
      }
    } else {
      pushSyncing.value = true
      try {
        await unregisterPushFromBackend()
        push.value = false
        persist()
      } catch {
        push.value = false
        persist()
        ui.pushToast('Не удалось отключить push на сервере', 'error')
      } finally {
        pushSyncing.value = false
      }
    }
  }

  function togglePush() {
    void setPush(!push.value)
  }

  function toggleSound() {
    sound.value = !sound.value
  }

  function toggleVibration() {
    vibration.value = !vibration.value
  }

  function toggleAiHints() {
    aiHints.value = !aiHints.value
  }

  function toggleAiSummary() {
    aiSummary.value = !aiSummary.value
  }

  return {
    push,
    sound,
    vibration,
    aiHints,
    aiSummary,
    pushSyncing,
    setPush,
    togglePush,
    toggleSound,
    toggleVibration,
    toggleAiHints,
    toggleAiSummary,
    persist,
  }
})
