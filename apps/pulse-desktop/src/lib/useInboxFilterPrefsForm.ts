import { computed, ref, watch, type Ref } from 'vue'
import { useAuthStore } from '../stores/authStore'
import { useChatStore } from '../stores/chatStore'
import { fetchUserDepartments, type DepartmentWithSource } from '../api/departments'
import { patchInboxFilterPreferences } from '../api/inboxFilterPreferences'
import type { InboxFilterPrefs } from '../types/dto/auth.types'

export const INBOX_CHANNEL_LABELS: Record<string, string> = {
  tg: 'Telegram',
  vk: 'VK',
  web: 'Сайт',
  max: 'MAX',
}

/**
 * Форма сохранённых фильтров инбокса (раздел в настройках).
 * @param externalDepartments если задан — список отделов снаружи; иначе загрузка внутри ensureDepartmentsLoaded.
 */
export function useInboxFilterPrefsForm(externalDepartments?: Ref<DepartmentWithSource[]>) {
  const authStore = useAuthStore()
  const chatStore = useChatStore()

  const internalDepartments = ref<DepartmentWithSource[]>([])
  const departmentsLoading = ref(false)
  const prefsSaving = ref(false)
  const prefsSaveError = ref<string | null>(null)
  const prefEnabledSources = ref<number[]>([])
  const prefEnabledChannels = ref<string[]>([])
  const prefEnabledDepartments = ref<number[]>([])

  const userSources = computed(() => authStore.user?.sources ?? [])

  const channelTypesInSources = computed(() => {
    const types = new Set<string>()
    for (const s of userSources.value) {
      if (s.type) {
        types.add(s.type)
      }
    }
    return ['tg', 'vk', 'web', 'max'].filter((t) => types.has(t))
  })

  const departments = computed(() =>
    externalDepartments != null ? externalDepartments.value : internalDepartments.value,
  )

  function syncLocalFromAuth(): void {
    const p = authStore.user?.inbox_filter_prefs
    const allSrcIds = userSources.value.map((s) => s.id)
    const depIds = departments.value.map((d) => d.id)
    const chTypes = channelTypesInSources.value

    if (!p?.enabled_source_ids?.length) {
      prefEnabledSources.value = [...allSrcIds]
    } else {
      prefEnabledSources.value = (p.enabled_source_ids ?? []).filter((id) => allSrcIds.includes(id))
    }

    if (!p?.enabled_channel_types?.length) {
      prefEnabledChannels.value = [...chTypes]
    } else {
      prefEnabledChannels.value = chTypes.filter((t) =>
        (p.enabled_channel_types ?? []).includes(t as 'tg'),
      )
    }

    if (!p?.enabled_department_ids?.length) {
      prefEnabledDepartments.value = [...depIds]
    } else {
      prefEnabledDepartments.value = depIds.filter((id) =>
        (p.enabled_department_ids ?? []).includes(id),
      )
    }
  }

  async function ensureDepartmentsLoaded(): Promise<void> {
    if (externalDepartments != null) {
      syncLocalFromAuth()
      return
    }
    if (internalDepartments.value.length > 0 || departmentsLoading.value) {
      syncLocalFromAuth()
      return
    }
    departmentsLoading.value = true
    prefsSaveError.value = null
    try {
      internalDepartments.value = await fetchUserDepartments()
    } catch {
      internalDepartments.value = []
      prefsSaveError.value = 'Не удалось загрузить рубрики.'
    } finally {
      departmentsLoading.value = false
    }
    syncLocalFromAuth()
  }

  watch(
    () => authStore.user?.inbox_filter_prefs,
    () => syncLocalFromAuth(),
    { deep: true },
  )

  watch([userSources, departments, channelTypesInSources], () => syncLocalFromAuth())

  async function saveInboxPrefsDefaults(): Promise<void> {
    const allSrcIds = userSources.value.map((s) => s.id)
    const depIds = departments.value.map((d) => d.id)
    const chTypes = channelTypesInSources.value

    const body: Partial<InboxFilterPrefs> = {}

    const srcEqual =
      prefEnabledSources.value.length === allSrcIds.length &&
      allSrcIds.every((id) => prefEnabledSources.value.includes(id))
    body.enabled_source_ids = srcEqual ? null : [...prefEnabledSources.value]

    const chEqual =
      prefEnabledChannels.value.length === chTypes.length &&
      chTypes.every((t) => prefEnabledChannels.value.includes(t))
    body.enabled_channel_types = chEqual
      ? null
      : ([...prefEnabledChannels.value] as InboxFilterPrefs['enabled_channel_types'])

    const depEqual =
      prefEnabledDepartments.value.length === depIds.length &&
      depIds.every((id) => prefEnabledDepartments.value.includes(id))
    body.enabled_department_ids = depEqual ? null : [...prefEnabledDepartments.value]

    prefsSaving.value = true
    prefsSaveError.value = null
    try {
      const data = await patchInboxFilterPreferences(body)
      authStore.applyUserProfile(data.user)
      await chatStore.syncInboxFiltersFromAuthUser(data.user)
    } catch (e: unknown) {
      prefsSaveError.value = e instanceof Error ? e.message : 'Не удалось сохранить.'
    } finally {
      prefsSaving.value = false
    }
  }

  function togglePrefId(arr: number[], id: number): number[] {
    return arr.includes(id) ? arr.filter((x) => x !== id) : [...arr, id]
  }

  function togglePrefStr(arr: string[], v: string): string[] {
    return arr.includes(v) ? arr.filter((x) => x !== v) : [...arr, v]
  }

  return {
    departmentsLoading,
    userSources,
    channelTypesInSources,
    departments,
    prefEnabledSources,
    prefEnabledChannels,
    prefEnabledDepartments,
    prefsSaving,
    prefsSaveError,
    syncLocalFromAuth,
    ensureDepartmentsLoaded,
    saveInboxPrefsDefaults,
    togglePrefId,
    togglePrefStr,
  }
}
