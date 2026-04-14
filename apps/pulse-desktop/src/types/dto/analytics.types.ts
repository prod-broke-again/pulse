export interface AnalyticsOverviewData {
  period: { from: string; to: string }
  chats_created: number
  chats_closed: number
  messages_total: number
  messages_from_clients: number
  messages_from_moderators: number
  messages_from_system: number
}

export interface AnalyticsOverviewResponse {
  data: AnalyticsOverviewData
}
