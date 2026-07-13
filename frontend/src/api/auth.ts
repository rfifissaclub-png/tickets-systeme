import { apiClient } from '@/lib/api-client'
import type { ApiSuccessResponse } from '@/types/api'
import type { User } from '@/types/models'

export interface LoginPayload {
  tenant?: string
  email: string
  password: string
  remember?: boolean
}

export interface LoginResponseData {
  user: User
  token: string
  expires_at: string | null
}

export async function login(payload: LoginPayload): Promise<LoginResponseData> {
  const { data } = await apiClient.post<ApiSuccessResponse<LoginResponseData>>('/auth/login', payload)
  return data.data
}

export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout')
}

export async function forgotPassword(payload: { tenant?: string; email: string }): Promise<string> {
  const { data } = await apiClient.post<ApiSuccessResponse<null>>('/auth/forgot-password', payload)
  return data.message ?? 'If that account exists, a password reset link has been sent.'
}

export interface ResetPasswordPayload {
  tenant?: string
  email: string
  token: string
  password: string
  password_confirmation: string
}

export async function resetPassword(payload: ResetPasswordPayload): Promise<string> {
  const { data } = await apiClient.post<ApiSuccessResponse<null>>('/auth/reset-password', payload)
  return data.message ?? 'Password reset successfully.'
}

export async function fetchMe(): Promise<User> {
  const { data } = await apiClient.get<ApiSuccessResponse<User>>('/me')
  return data.data
}

export interface VerifyEmailPayload {
  id: string
  hash: string
  expires: string
  signature: string
}

/**
 * Replays the exact id/hash/expires/signature the backend's signed link
 * carried — the `signed` middleware validates the request's URL against
 * that signature server-side, so all four must be forwarded unchanged (see
 * AppServiceProvider::boot()'s VerifyEmail::createUrlUsing()).
 */
export async function verifyEmail(payload: VerifyEmailPayload): Promise<string> {
  const { id, hash, ...query } = payload
  const { data } = await apiClient.get<ApiSuccessResponse<null>>(`/auth/email/verify/${id}/${hash}`, {
    params: query,
  })
  return data.message ?? 'Email verified successfully.'
}

export async function resendVerificationEmail(): Promise<string> {
  const { data } = await apiClient.post<ApiSuccessResponse<null>>('/auth/email/verification-notification')
  return data.message ?? 'Verification link sent.'
}
