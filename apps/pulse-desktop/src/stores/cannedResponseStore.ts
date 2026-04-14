import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchCannedResponses } from '../api/canned-responses'
import type { ApiCannedResponse } from '../types/dto/canned-response.types'

export const useCannedResponseStore = defineStore('cannedResponse', () => {
  const responses = ref<ApiCannedResponse[]>([])
  const isLoading = ref(false)

  async function loadResponses(params: { source_id?: number; q?: string } = {}) {
    isLoading.value = true
    try {
      responses.value = await fetchCannedResponses(params)
    } catch (e) {
      console.error('Failed to load canned responses:', e)
    } finally {
      isLoading.value = false
    }
  }

  return {
    responses,
    isLoading,
    loadResponses,
  }
})
