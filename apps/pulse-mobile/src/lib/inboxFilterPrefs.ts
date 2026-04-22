import type { ChatListFilters } from '../api/chatRepository'
import type { InboxFilterPrefs } from '../types/inbox'

/** List query fields driven by saved inbox prefs (no tab/page/search/status). */
export type InboxPrefsSlice = Pick<ChatListFilters, 'source_ids' | 'department_ids' | 'channels'>

/** Map saved prefs → list filter params (`undefined` = no restriction). */
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
