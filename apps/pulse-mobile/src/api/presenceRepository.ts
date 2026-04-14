import { http } from '../lib/http'

export type ModeratorPresenceState = {
  manual_online: boolean
  last_heartbeat_at: string | null
  last_activity_at: string | null
  is_online: boolean
  is_away: boolean
}

export async function fetchModeratorPresenceMe(): Promise<ModeratorPresenceState> {
  const { data } = await http.get<{ data: ModeratorPresenceState }>('/moderator/presence/me')
  return data.data
}

export async function toggleModeratorPresence(online: boolean): Promise<ModeratorPresenceState> {
  const { data } = await http.post<{ data: ModeratorPresenceState }>('/moderator/presence/toggle', { online })
  return data.data
}

export async function sendModeratorPresenceHeartbeat(): Promise<ModeratorPresenceState> {
  const { data } = await http.post<{ data: ModeratorPresenceState }>('/moderator/presence/heartbeat')
  return data.data
}
