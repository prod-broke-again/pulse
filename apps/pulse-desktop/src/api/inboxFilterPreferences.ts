import { api } from './client'
import type { ApiUser, InboxFilterPrefs } from '../types/dto/auth.types'

export async function fetchInboxFilterPreferences(): Promise<{ inbox_filter_prefs: InboxFilterPrefs }> {
  const res = await api.get<{ data: { inbox_filter_prefs: InboxFilterPrefs } }>(
    '/user/inbox-filter-preferences',
  )
  return res.data
}

export async function patchInboxFilterPreferences(
  patch: Partial<InboxFilterPrefs>,
): Promise<{ inbox_filter_prefs: InboxFilterPrefs; user: ApiUser }> {
  const res = await api.patch<{ data: { inbox_filter_prefs: InboxFilterPrefs; user: ApiUser } }>(
    '/user/inbox-filter-preferences',
    patch as Record<string, unknown>,
  )
  return res.data
}
