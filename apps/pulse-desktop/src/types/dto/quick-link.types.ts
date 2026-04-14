export interface ApiQuickLink {
  id: number
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
