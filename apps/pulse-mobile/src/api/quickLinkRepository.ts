import { http } from '../lib/http'
import type { ApiQuickLink } from './types'

export async function fetchQuickLinks(params?: {
  source_id?: number
  q?: string
  include_inactive?: boolean
}): Promise<ApiQuickLink[]> {
  const res = await http.get<{ data: ApiQuickLink[] }>('/quick-links', {
    params: {
      ...params,
      include_inactive: params?.include_inactive ? 1 : undefined,
    },
  })
  return res.data.data
}

export async function createQuickLink(body: {
  source_id?: number | null
  title: string
  url: string
  is_active?: boolean
  sort_order?: number
}): Promise<ApiQuickLink> {
  const res = await http.post<{ data: ApiQuickLink }>('/quick-links', body)
  return res.data.data
}

export async function updateQuickLink(
  id: number,
  body: Partial<{
    source_id: number | null
    title: string
    url: string
    is_active: boolean
    sort_order: number
  }>,
): Promise<ApiQuickLink> {
  const res = await http.patch<{ data: ApiQuickLink }>(`/quick-links/${id}`, body)
  return res.data.data
}

export async function deleteQuickLink(id: number): Promise<void> {
  await http.delete(`/quick-links/${id}`)
}

export async function reorderQuickLinks(orders: { id: number; sort_order: number }[]): Promise<void> {
  await http.post('/quick-links/reorder', { orders })
}
