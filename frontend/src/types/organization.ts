export type ParseStatus =
  | 'not_started'
  | 'pending'
  | 'processing'
  | 'completed'
  | 'failed'

export interface Organization {
  id: number
  source_url: string
  normalized_url: string | null
  title: string | null
  address: string | null
  parse_status: ParseStatus
  parse_status_label: string
  rating: number | null
  ratings_count: number | null
  reviews_count: number | null
  loaded_reviews_count: number
  is_partial: boolean
  last_parsed_at: string | null
  last_error: string | null
}
