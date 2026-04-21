import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchQuickLinks } from '../api/quick-links'
import type { SourceListQueryParams } from '../api/sourceListQuery'
import type { ApiQuickLink } from '../types/dto/quick-link.types'

export const useQuickLinkStore = defineStore('quickLink', () => {
  const links = ref<ApiQuickLink[]>([])
  const isLoading = ref(false)

  async function loadLinks(params: SourceListQueryParams = {}) {
    isLoading.value = true
    try {
      links.value = await fetchQuickLinks({ ...params, include_inactive: false })
    } catch (e) {
      console.error('Failed to load quick links:', e)
    } finally {
      isLoading.value = false
    }
  }

  return {
    links,
    isLoading,
    loadLinks,
  }
})
