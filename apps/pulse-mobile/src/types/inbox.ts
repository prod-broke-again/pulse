/** Saved inbox default filters (from GET/PATCH /user/inbox-filter-preferences). */
export interface InboxFilterPrefs {
  enabled_source_ids?: number[] | null
  enabled_channel_types?: Array<'tg' | 'vk' | 'web' | 'max'> | null
  enabled_department_ids?: number[] | null
}
