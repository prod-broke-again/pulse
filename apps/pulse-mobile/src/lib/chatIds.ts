/**
 * Chat/thread IDs are routed as strings (e.g. from Vue Router); API expects finite numbers.
 * Centralize parsing and sorting so we don't scatter Number(...) + isFinite checks.
 */

/** Parse a string chat id from UI/navigation into a numeric id for API calls. */
export function parseApiChatId(chatId: string | null | undefined): number | null {
  if (chatId == null || chatId === '') {
    return null
  }
  const n = Number(chatId)
  return Number.isFinite(n) ? n : null
}

/**
 * For sorting/merging: numeric message ids from the API; optimistic temp ids sort last among non-numeric.
 */
export function messageIdSortKey(id: string): number {
  const n = Number(id)
  if (Number.isFinite(n) && !id.startsWith('temp-')) {
    return n
  }
  return Number.MAX_SAFE_INTEGER
}

export function compareMessageIds(a: string, b: string): number {
  const na = messageIdSortKey(a)
  const nb = messageIdSortKey(b)
  if (na !== nb) {
    return na - nb
  }
  return String(a).localeCompare(String(b))
}

/** Largest numeric API message id in the list (ignores temp-* optimistic ids). */
export function maxNumericMessageIdFromList(ids: Iterable<string>): number {
  let max = 0
  for (const id of ids) {
    if (typeof id === 'string' && id.startsWith('temp-')) {
      continue
    }
    const n = Number(id)
    if (Number.isFinite(n) && n > max) {
      max = n
    }
  }
  return max
}
