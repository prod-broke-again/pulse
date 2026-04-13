import { http } from '../lib/http'
import { buildSourceListParams, type SourceListQueryParams } from './sourceListQueryParams'
import type { ApiCannedResponse } from './types'

export async function fetchCannedResponses(params?: SourceListQueryParams): Promise<ApiCannedResponse[]> {
  const res = await http.get<{ data: ApiCannedResponse[] }>('/canned-responses', {
    params: buildSourceListParams(params),
  })
  return res.data.data
}

export async function createCannedResponse(body: {
  source_id?: number | null
  code: string
  title: string
  text: string
  is_active?: boolean
}): Promise<ApiCannedResponse> {
  const res = await http.post<{ data: ApiCannedResponse }>('/canned-responses', body)
  return res.data.data
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
  const res = await http.patch<{ data: ApiCannedResponse }>(`/canned-responses/${id}`, body)
  return res.data.data
}

export async function deleteCannedResponse(id: number): Promise<void> {
  await http.delete(`/canned-responses/${id}`)
}
