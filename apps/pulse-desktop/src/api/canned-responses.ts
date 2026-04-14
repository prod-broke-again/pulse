import { api } from './client'
import { buildSourceListQuery, type SourceListQueryParams } from './sourceListQuery'
import type {
  ApiCannedResponse,
  CannedResponseListResponse,
  CannedResponseSingleResponse,
} from '../types/dto/canned-response.types'

export async function fetchCannedResponses(
  params: SourceListQueryParams = {},
): Promise<ApiCannedResponse[]> {
  const response = await api.get<CannedResponseListResponse>(
    '/canned-responses',
    buildSourceListQuery(params),
  )
  return response.data
}

export async function createCannedResponse(body: {
  source_id?: number | null
  code: string
  title: string
  text: string
  is_active?: boolean
}): Promise<ApiCannedResponse> {
  const response = await api.post<CannedResponseSingleResponse>('/canned-responses', body)
  return response.data
}

export async function updateCannedResponse(
  id: number,
  body: Partial<{
    source_id: number | null
    code: string
    title: string
    text: string
    is_active: boolean
  }>,
): Promise<ApiCannedResponse> {
  const response = await api.patch<CannedResponseSingleResponse>(`/canned-responses/${id}`, body)
  return response.data
}

export async function deleteCannedResponse(id: number): Promise<void> {
  await api.delete<{ data: { ok: boolean } }>(`/canned-responses/${id}`)
}
