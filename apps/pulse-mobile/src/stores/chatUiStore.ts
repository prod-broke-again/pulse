import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * AI assist overlay / panel (открытие только по кнопке, без фиктивного таймера).
 */
export const useChatUiStore = defineStore('chatUi', () => {
  const overlayVisible = ref(false)
  const panelOpen = ref(false)
  /** Пока грузим резюме/подсказки при первом открытии. */
  const aiProcessing = ref(false)

  let aiTimers: number[] = []

  function clearAiTimers() {
    aiTimers.forEach((t) => window.clearTimeout(t))
    aiTimers = []
  }

  function openAiPanel() {
    clearAiTimers()
    overlayVisible.value = true
    aiProcessing.value = true
    const t1 = window.setTimeout(() => {
      panelOpen.value = true
    }, 10)
    aiTimers.push(t1)
  }

  function setAiPanelContentReady() {
    aiProcessing.value = false
  }

  function closeAiPanel() {
    panelOpen.value = false
    const t = window.setTimeout(() => {
      overlayVisible.value = false
      aiProcessing.value = false
    }, 350)
    aiTimers.push(t)
  }

  return {
    overlayVisible,
    panelOpen,
    aiProcessing,
    openAiPanel,
    closeAiPanel,
    setAiPanelContentReady,
    clearAiTimers,
  }
})
