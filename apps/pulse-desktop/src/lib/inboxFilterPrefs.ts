import type { ChatListFilters } from '../types/dto/chat.types'
import type { InboxFilterPrefs } from '../types/dto/auth.types'

/** Поля запроса списка чатов, задаваемые сохранёнными prefs (без tab/page/search/status). */
export type InboxPrefsSlice = Pick<
  ChatListFilters,
  'source_ids' | 'department_ids' | 'channels'
>

/** Маппинг сохранённых prefs → параметры фильтра списка (undefined = без ограничения по полю). */
export function pendingListFiltersFromPrefs(prefs: InboxFilterPrefs | undefined | null): Partial<InboxPrefsSlice> {
  if (!prefs) {
    return {}
  }
  const out: Partial<InboxPrefsSlice> = {}
  if (prefs.enabled_source_ids != null && prefs.enabled_source_ids.length > 0) {
    out.source_ids = [...prefs.enabled_source_ids]
  }
  if (prefs.enabled_department_ids != null && prefs.enabled_department_ids.length > 0) {
    out.department_ids = [...prefs.enabled_department_ids]
  }
  if (prefs.enabled_channel_types != null && prefs.enabled_channel_types.length > 0) {
    out.channels = [...prefs.enabled_channel_types] as ChatListFilters['channels']
  }
  return out
}
