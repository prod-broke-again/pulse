import { http } from '../lib/http'
import type { ApiCannedResponse } from './types'

export async function fetchCannedResponses(params?: {
  source_id?: number
  q?: string
  include_inactive?: boolean
}): Promise<ApiCannedResponse[]> {
  const res = await http.get<{ data: ApiCannedResponse[] }>('/canned-responses', {
    params: {
      ...params,
      include_inactive: params?.include_inactive ? 1 : undefined,
    },
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
