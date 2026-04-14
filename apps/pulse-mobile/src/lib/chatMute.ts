export function isMutedUntilActive(mutedUntil: string | null | undefined): boolean {
  if (mutedUntil == null || mutedUntil === '') {
    return false
  }
  return new Date(mutedUntil).getTime() > Date.now()
}
