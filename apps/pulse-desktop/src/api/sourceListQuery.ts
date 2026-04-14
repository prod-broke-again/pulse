/**
 * Shared query shape for listing canned responses and quick links by source.
 */
export type SourceListQueryParams = {
  source_id?: number
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
  if (params.q != null && params.q !== '') {
    query.q = params.q
  }
  if (params.include_inactive) {
    query.include_inactive = 1
  }
  return query
}
