import { api } from './client'

export type ModeratorPresenceState = {
  manual_online: boolean
  last_heartbeat_at: string | null
  last_activity_at: string | null
  is_online: boolean
  is_away: boolean
}

type PresenceResponse = { data: ModeratorPresenceState }

export async function fetchModeratorPresenceMe(): Promise<ModeratorPresenceState> {
  const res = await api.get<PresenceResponse>('/moderator/presence/me')
  return res.data
}

export async function toggleModeratorPresence(online: boolean): Promise<ModeratorPresenceState> {
  const res = await api.post<PresenceResponse>('/moderator/presence/toggle', { online })
  return res.data
}

export async function sendModeratorPresenceHeartbeat(): Promise<ModeratorPresenceState> {
  const res = await api.post<PresenceResponse>('/moderator/presence/heartbeat')
  return res.data
}
