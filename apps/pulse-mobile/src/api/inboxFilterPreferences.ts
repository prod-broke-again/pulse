import { http } from '../lib/http'
import type { InboxFilterPrefs } from '../types/inbox'
import type { PulseUser } from '../stores/authStore'

export async function fetchInboxFilterPreferences(): Promise<{ inbox_filter_prefs: InboxFilterPrefs }> {
  const res = await http.get<{ data: { inbox_filter_prefs: InboxFilterPrefs } }>('/user/inbox-filter-preferences')
  return res.data.data
}

export async function patchInboxFilterPreferences(
  patch: Partial<InboxFilterPrefs>,
): Promise<{ inbox_filter_prefs: InboxFilterPrefs; user: PulseUser }> {
  const res = await http.patch<{ data: { inbox_filter_prefs: InboxFilterPrefs; user: PulseUser } }>(
    '/user/inbox-filter-preferences',
    patch,
  )
  return res.data.data
}
