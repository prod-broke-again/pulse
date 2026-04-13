/**
 * Shared query shape for listing canned responses and quick links by source.
 */
export type SourceListQueryParams = {
  source_id?: number
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
  if (params?.q != null && params.q !== '') {
    out.q = params.q
  }
  if (params?.include_inactive) {
    out.include_inactive = 1
  }
  return out
}
