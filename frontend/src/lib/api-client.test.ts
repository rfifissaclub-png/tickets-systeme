import { AxiosError } from 'axios'
import { describe, expect, it, vi } from 'vitest'

// toApiError() falls back to i18next.t('common.unexpectedError') for any
// error it can't read a real message from — mocked rather than booting the
// real i18n setup, so these tests don't depend on locale files loading.
vi.mock('i18next', () => ({
  default: { t: (key: string) => key },
}))

import { ApiRequestError, toApiError } from './api-client'

function axiosErrorWithResponse(status: number, data: Record<string, unknown>): AxiosError {
  const error = new AxiosError('Request failed', 'ERR_BAD_REQUEST')
  // @ts-expect-error -- AxiosResponse has more required fields than this
  // test needs; toApiError only ever reads .status and .data off it.
  error.response = { status, data }
  return error
}

describe('toApiError', () => {
  it('reads message/code/errors straight from the response body', () => {
    const result = toApiError(
      axiosErrorWithResponse(422, {
        message: 'The given data was invalid.',
        code: 'validation_failed',
        errors: { email: ['The email field is required.'] },
      }),
    )

    expect(result.status).toBe(422)
    expect(result.message).toBe('The given data was invalid.')
    expect(result.code).toBe('validation_failed')
    expect(result.errors).toEqual({ email: ['The email field is required.'] })
  })

  it('falls back to the axios error message when there is no response (network error)', () => {
    const error = new AxiosError('Network Error', 'ERR_NETWORK')
    const result = toApiError(error)

    expect(result.status).toBeUndefined()
    expect(result.message).toBe('Network Error')
    expect(result.code).toBeNull()
    expect(result.errors).toBeNull()
  })

  it('falls back to the generic i18n message for a non-axios error', () => {
    const result = toApiError(new Error('some unrelated thrown error'))

    expect(result.message).toBe('common.unexpectedError')
    expect(result.status).toBeUndefined()
    expect(result.code).toBeNull()
    expect(result.errors).toBeNull()
  })
})

describe('ApiRequestError.firstError', () => {
  it('returns null when there are no errors at all', () => {
    expect(new ApiRequestError('msg', 422, null, null).firstError()).toBeNull()
  })

  it('returns null when errors is present but empty', () => {
    expect(new ApiRequestError('msg', 422, null, {}).firstError()).toBeNull()
  })

  it("returns the first field's first message", () => {
    const error = new ApiRequestError('msg', 422, 'validation_failed', {
      agent_id: ["The selected agent is not qualified for this ticket's type."],
      subject: ['The subject field is required.'],
    })

    expect(error.firstError()).toBe("The selected agent is not qualified for this ticket's type.")
  })
})
