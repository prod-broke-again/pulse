/**
 * Shared query shape for listing canned responses and quick links.
 */
export type SourceListQueryParams = {
  source_id?: number
  department_id?: number
  /** When true, list entries relevant to a chat (global + matching source + matching department). */
  chat_context?: boolean
  visibility?: 'mine' | 'shared' | 'all'
  scope_type?: 'source' | 'department'
  scope_id?: number
  q?: string
  include_inactive?: boolean
}

/** Builds GET query object for `/canned-responses` and `/quick-links` list endpoints. */
export function buildSourceListQuery(
  params: SourceListQueryParams = {},
): Record<string, string | number | undefined> {
  const query: Record<string, string | number | undefined> = {}
  if (params.source_id != null) {
    query.source_id = params.source_id
  }
  if (params.department_id != null) {
    query.department_id = params.department_id
  }
  if (params.chat_context === true) {
    query.chat_context = 1
  }
  if (params.visibility != null) {
    query.visibility = params.visibility
  }
  if (params.scope_type != null) {
    query.scope_type = params.scope_type
  }
  if (params.scope_id != null) {
    query.scope_id = params.scope_id
  }
  if (params.q != null && params.q !== '') {
    query.q = params.q
  }
  if (params.include_inactive) {
    query.include_inactive = 1
  }
  return query
}
