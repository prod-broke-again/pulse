import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * AI assist overlay / panel animation state (decoupled from thread messages).
 */
export const useChatUiStore = defineStore('chatUi', () => {
  const overlayVisible = ref(false)
  const panelOpen = ref(false)
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
    const t2 = window.setTimeout(() => {
      aiProcessing.value = false
    }, 1200)
    aiTimers.push(t1, t2)
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
    clearAiTimers,
  }
})
