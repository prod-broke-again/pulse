import { api } from './client'
import { buildSourceListQuery, type SourceListQueryParams } from './sourceListQuery'
import type {
  ApiQuickLink,
  QuickLinkListResponse,
  QuickLinkSingleResponse,
} from '../types/dto/quick-link.types'

export async function fetchQuickLinks(params: SourceListQueryParams = {}): Promise<ApiQuickLink[]> {
  const response = await api.get<QuickLinkListResponse>('/quick-links', buildSourceListQuery(params))
  return response.data
}

export async function createQuickLink(body: {
  source_id?: number | null
  title: string
  url: string
  is_active?: boolean
  sort_order?: number
}): Promise<ApiQuickLink> {
  const response = await api.post<QuickLinkSingleResponse>('/quick-links', body)
  return response.data
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
  const response = await api.patch<QuickLinkSingleResponse>(`/quick-links/${id}`, body)
  return response.data
}

export async function deleteQuickLink(id: number): Promise<void> {
  await api.delete<{ data: { ok: boolean } }>(`/quick-links/${id}`)
}

export async function reorderQuickLinks(
  orders: { id: number; sort_order: number }[],
): Promise<void> {
  await api.post<{ data: { ok: boolean } }>('/quick-links/reorder', { orders })
}
