export interface ApiQuickLink {
  id: number
  owner_user_id: number | null
  is_shared: boolean
  scope_type: 'source' | 'department' | null
  scope_id: number | null
  source_id: number | null
  title: string
  url: string
  is_active: boolean
  sort_order: number
}

export interface QuickLinkListResponse {
  data: ApiQuickLink[]
}

export interface QuickLinkSingleResponse {
  data: ApiQuickLink
}
