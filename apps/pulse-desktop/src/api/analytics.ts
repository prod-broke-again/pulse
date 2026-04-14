import { api } from './client'
import type { AnalyticsOverviewData, AnalyticsOverviewResponse } from '../types/dto/analytics.types'

export async function fetchAnalyticsOverview(params: {
  from: string
  to: string
  source_id?: number
}): Promise<AnalyticsOverviewData> {
  const response = await api.get<AnalyticsOverviewResponse>('/analytics/overview', {
    from: params.from,
    to: params.to,
    source_id: params.source_id,
  })
  return response.data
}
