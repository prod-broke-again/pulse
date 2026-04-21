/**
 * Shared query shape for listing canned responses and quick links.
 */
export type SourceListQueryParams = {
  source_id?: number
  department_id?: number
  chat_context?: boolean
  visibility?: 'mine' | 'shared' | 'all'
  scope_type?: 'source' | 'department'
  scope_id?: number
  q?: string
  include_inactive?: boolean
}

/** Builds axios `params` for `/canned-responses` and `/quick-links` list endpoints. */
export function buildSourceListParams(
  params?: SourceListQueryParams,
): Record<string, number | string | undefined> {
  const out: Record<string, number | string | undefined> = {}
  if (params?.source_id != null) {
    out.source_id = params.source_id
  }
  if (params?.department_id != null) {
    out.department_id = params.department_id
  }
  if (params?.chat_context === true) {
    out.chat_context = 1
  }
  if (params?.visibility != null) {
    out.visibility = params.visibility
  }
  if (params?.scope_type != null) {
    out.scope_type = params.scope_type
  }
  if (params?.scope_id != null) {
    out.scope_id = params.scope_id
  }
  if (params?.q != null && params.q !== '') {
    out.q = params.q
  }
  if (params?.include_inactive) {
    out.include_inactive = 1
  }
  return out
}
