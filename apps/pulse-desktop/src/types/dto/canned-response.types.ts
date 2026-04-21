export interface ApiCannedResponse {
  id: number
  owner_user_id: number | null
  is_shared: boolean
  scope_type: 'source' | 'department' | null
  scope_id: number | null
  /** Derived when scope is a source; backward compatible. */
  source_id: number | null
  code: string
  title: string
  text: string
  is_active: boolean
}

export interface CannedResponseListResponse {
  data: ApiCannedResponse[]
}

export interface CannedResponseSingleResponse {
  data: ApiCannedResponse
}
