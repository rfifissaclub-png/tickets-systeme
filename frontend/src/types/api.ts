/**
 * Mirrors App\Support\ApiResponse on the backend — every endpoint returns
 * this shape, success or error, so the client never has to branch per route.
 */
export interface ApiSuccessResponse<T> {
  success: true
  message: string | null
  data: T
  meta: Record<string, unknown> | null
}

export interface ApiErrorResponse {
  success: false
  message: string
  errors: Record<string, string[]> | null
  code: string | null
}

export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}
