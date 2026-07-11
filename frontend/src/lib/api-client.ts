import axios, { AxiosError } from 'axios'
import i18next from 'i18next'

import { useAuthStore } from '@/stores/auth-store'
import { useLocaleStore } from '@/stores/locale-store'
import type { ApiErrorResponse } from '@/types/api'

export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8080/api/v1',
  headers: {
    Accept: 'application/json',
  },
})

apiClient.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  // Keeps validation/domain messages from the API in the user's selected UI
  // language — matches the backend's SetLocale middleware negotiation.
  config.headers['Accept-Language'] = useLocaleStore.getState().locale
  return config
})

apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorResponse>) => {
    // A 401 means the token is gone/expired server-side — drop the local
    // session so the UI falls back to the login page instead of silently
    // re-sending an invalid token on every subsequent request.
    if (error.response?.status === 401) {
      useAuthStore.getState().logout()
    }

    return Promise.reject(error)
  },
)

export class ApiRequestError extends Error {
  readonly status: number | undefined
  readonly code: string | null
  readonly errors: Record<string, string[]> | null

  constructor(
    message: string,
    status: number | undefined,
    code: string | null,
    errors: Record<string, string[]> | null,
  ) {
    super(message)
    this.name = 'ApiRequestError'
    this.status = status
    this.code = code
    this.errors = errors
  }

  /**
   * The first validation message across all fields, for a quick inline
   * error display when a form doesn't map errors field-by-field.
   */
  firstError(): string | null {
    if (!this.errors) return null
    const first = Object.values(this.errors)[0]
    return first?.[0] ?? null
  }
}

export function toApiError(error: unknown): ApiRequestError {
  if (axios.isAxiosError<ApiErrorResponse>(error)) {
    const data = error.response?.data
    return new ApiRequestError(
      data?.message ?? error.message ?? i18next.t('common.unexpectedError'),
      error.response?.status,
      data?.code ?? null,
      data?.errors ?? null,
    )
  }

  return new ApiRequestError(i18next.t('common.unexpectedError'), undefined, null, null)
}
