import { describe, it, expect } from 'vitest'
import { apiErrorMessage } from './api'

describe('apiErrorMessage', () => {
  it('prefers the first field validation error', () => {
    const error = {
      isAxiosError: true,
      response: { data: { message: 'Validation failed', errors: { email: ['The email is invalid.'] } } },
    }
    expect(apiErrorMessage(error)).toBe('The email is invalid.')
  })

  it('falls back to the top-level message', () => {
    const error = { isAxiosError: true, response: { data: { message: 'Resource not found.' } } }
    expect(apiErrorMessage(error)).toBe('Resource not found.')
  })

  it('reports a friendly message for a network error', () => {
    const error = { isAxiosError: true, code: 'ERR_NETWORK' }
    expect(apiErrorMessage(error)).toContain('Cannot reach the API')
  })

  it('uses the provided fallback for non-axios errors', () => {
    expect(apiErrorMessage(new Error('boom'), 'fallback message')).toBe('fallback message')
  })
})
