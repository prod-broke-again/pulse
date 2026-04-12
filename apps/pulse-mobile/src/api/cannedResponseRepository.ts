import { http } from '../lib/http'
import type { ApiCannedResponse } from './types'

export async function fetchCannedResponses(params?: {
  source_id?: number
  q?: string
}): Promise<ApiCannedResponse[]> {
  const res = await http.get<{ data: ApiCannedResponse[] }>('/canned-responses', { params })
  return res.data.data
}
