export interface ApiCannedResponse {
  id: number
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
