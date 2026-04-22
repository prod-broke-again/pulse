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

/** Выбор группы: всё / частично / ничего (для чекбоксов с indeterminate). */
export type InboxPrefTriState = 'all' | 'partial' | 'none'

export interface InboxGroupedSourceRow {
  id: number
  name: string
  type: string
  departments: DepartmentWithSource[]
  triState: InboxPrefTriState
}

export interface InboxGroupedPlatformRow {
  type: string
  label: string
  sources: InboxGroupedSourceRow[]
  triState: InboxPrefTriState
}

function uniqNums(ids: number[]): number[] {
  return [...new Set(ids)]
}

function uniqStrs(ids: string[]): string[] {
  return [...new Set(ids)]
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

  function departmentIdsForSource(sourceId: number): number[] {
    return departments.value.filter((d) => d.source_id === sourceId).map((d) => d.id)
  }

  function sourceIdsForPlatform(platformType: string): number[] {
    return userSources.value.filter((s) => s.type === platformType).map((s) => s.id)
  }

  function departmentIdsForPlatform(platformType: string): number[] {
    const sids = sourceIdsForPlatform(platformType)
    return departments.value.filter((d) => sids.includes(d.source_id)).map((d) => d.id)
  }

  function platformTriState(platformType: string): InboxPrefTriState {
    const channelOn = prefEnabledChannels.value.includes(platformType)
    const srcIds = sourceIdsForPlatform(platformType)
    const depIds = departmentIdsForPlatform(platformType)
    if (srcIds.length === 0) {
      return 'none'
    }
    if (!channelOn) {
      return 'none'
    }
    const allSrc = srcIds.every((id) => prefEnabledSources.value.includes(id))
    const allDep =
      depIds.length === 0 || depIds.every((id) => prefEnabledDepartments.value.includes(id))
    if (allSrc && allDep) {
      return 'all'
    }
    return 'partial'
  }

  function sourceTriState(sourceId: number): InboxPrefTriState {
    const depIds = departmentIdsForSource(sourceId)
    const srcOn = prefEnabledSources.value.includes(sourceId)
    if (depIds.length === 0) {
      return srcOn ? 'all' : 'none'
    }
    const depsOn = depIds.filter((id) => prefEnabledDepartments.value.includes(id))
    if (!srcOn && depsOn.length === 0) {
      return 'none'
    }
    if (srcOn && depsOn.length === depIds.length) {
      return 'all'
    }
    return 'partial'
  }

  const groupedPlatforms = computed((): InboxGroupedPlatformRow[] =>
    channelTypesInSources.value.map((type) => ({
      type,
      label: INBOX_CHANNEL_LABELS[type] ?? type,
      sources: userSources.value
        .filter((s) => s.type === type)
        .map((s) => ({
          id: s.id,
          name: s.name,
          type: s.type,
          departments: departments.value.filter((d) => d.source_id === s.id),
          triState: sourceTriState(s.id),
        })),
      triState: platformTriState(type),
    })),
  )

  /** Краткая сводка для подзаголовка блока настроек. */
  const prefsSummaryLines = computed(() => {
    const rows = groupedPlatforms.value
    if (rows.length === 0) {
      return ['Нет привязанных площадок — сохранять ограничения нечего.']
    }
    if (rows.every((p) => p.triState === 'all')) {
      return ['По умолчанию: все доступные площадки, источники и рубрики.']
    }
    const lines: string[] = []
    for (const p of rows) {
      if (p.triState === 'none') {
        continue
      }
      const srcCount = p.sources.filter((s) => s.triState !== 'none').length
      const totalSrc = p.sources.length
      if (p.triState === 'all') {
        lines.push(`${p.label}: все источники (${totalSrc})`)
      } else {
        lines.push(`${p.label}: выбрано источников ${srcCount} из ${totalSrc}`)
      }
    }
    if (lines.length === 0) {
      return ['Все площадки отключены — после сохранения обращения по ним не попадут в инбокс.']
    }
    return lines
  })

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

  /**
   * Включить или выключить целиком площадку (канал + все источники этого типа + все их рубрики).
   */
  function setPlatformFullyEnabled(platformType: string, enabled: boolean): void {
    const srcIds = sourceIdsForPlatform(platformType)
    const depIds = departmentIdsForPlatform(platformType)
    if (enabled) {
      prefEnabledChannels.value = uniqStrs([...prefEnabledChannels.value, platformType])
      prefEnabledSources.value = uniqNums([...prefEnabledSources.value, ...srcIds])
      prefEnabledDepartments.value = uniqNums([...prefEnabledDepartments.value, ...depIds])
    } else {
      prefEnabledChannels.value = prefEnabledChannels.value.filter((t) => t !== platformType)
      prefEnabledSources.value = prefEnabledSources.value.filter((id) => !srcIds.includes(id))
      prefEnabledDepartments.value = prefEnabledDepartments.value.filter((id) => !depIds.includes(id))
    }
  }

  /**
   * Клик по «мастер»-чекбоксу площадки: из partial/none — включить всё; из all — выключить всё.
   */
  function onPlatformMasterClick(platformType: string): void {
    const st = platformTriState(platformType)
    if (st === 'all') {
      setPlatformFullyEnabled(platformType, false)
    } else {
      setPlatformFullyEnabled(platformType, true)
    }
  }

  /**
   * Включить или выключить источник и все его рубрики; при отключении последнего источника типа убирает канал.
   */
  function setSourceFullyEnabled(sourceId: number, enabled: boolean): void {
    const source = userSources.value.find((s) => s.id === sourceId)
    const depIds = departmentIdsForSource(sourceId)
    const platformType = source?.type
    if (enabled) {
      if (platformType) {
        prefEnabledChannels.value = uniqStrs([...prefEnabledChannels.value, platformType])
      }
      prefEnabledSources.value = uniqNums([...prefEnabledSources.value, sourceId])
      prefEnabledDepartments.value = uniqNums([...prefEnabledDepartments.value, ...depIds])
    } else {
      prefEnabledSources.value = prefEnabledSources.value.filter((id) => id !== sourceId)
      prefEnabledDepartments.value = prefEnabledDepartments.value.filter((id) => !depIds.includes(id))
      if (platformType) {
        const anyLeft = userSources.value.some(
          (s) => s.type === platformType && prefEnabledSources.value.includes(s.id),
        )
        if (!anyLeft) {
          prefEnabledChannels.value = prefEnabledChannels.value.filter((t) => t !== platformType)
        }
      }
    }
  }

  function onSourceMasterClick(sourceId: number): void {
    const st = sourceTriState(sourceId)
    if (st === 'all') {
      setSourceFullyEnabled(sourceId, false)
    } else {
      setSourceFullyEnabled(sourceId, true)
    }
  }

  /** Переключить одну рубрику; при включении подтягивает источник и канал. */
  function toggleDepartmentPref(departmentId: number): void {
    const dept = departments.value.find((d) => d.id === departmentId)
    if (!dept) {
      return
    }
    const has = prefEnabledDepartments.value.includes(departmentId)
    if (has) {
      prefEnabledDepartments.value = prefEnabledDepartments.value.filter((id) => id !== departmentId)
      const stillAny = departments.value.some(
        (d) => d.source_id === dept.source_id && prefEnabledDepartments.value.includes(d.id),
      )
      if (!stillAny) {
        setSourceFullyEnabled(dept.source_id, false)
      }
    } else {
      const source = userSources.value.find((s) => s.id === dept.source_id)
      if (source?.type) {
        prefEnabledChannels.value = uniqStrs([...prefEnabledChannels.value, source.type])
      }
      prefEnabledSources.value = uniqNums([...prefEnabledSources.value, dept.source_id])
      prefEnabledDepartments.value = uniqNums([...prefEnabledDepartments.value, departmentId])
    }
  }

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
    groupedPlatforms,
    prefsSummaryLines,
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
    setPlatformFullyEnabled,
    setSourceFullyEnabled,
    onPlatformMasterClick,
    onSourceMasterClick,
    toggleDepartmentPref,
    platformTriState,
    sourceTriState,
  }
}
