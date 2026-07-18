import { describe, expect, it } from 'vitest'

import { formatDuration } from './format'

describe('formatDuration', () => {
  it('renders an em dash for a null duration (no data yet)', () => {
    expect(formatDuration(null)).toBe('—')
  })

  it('renders whole seconds under a minute', () => {
    expect(formatDuration(0)).toBe('0s')
    expect(formatDuration(45)).toBe('45s')
  })

  it('renders whole minutes under an hour', () => {
    expect(formatDuration(90)).toBe('2m')
    expect(formatDuration(1800)).toBe('30m')
  })

  it('renders hours to one decimal place under a day', () => {
    expect(formatDuration(3661)).toBe('1.0h')
    expect(formatDuration(7200)).toBe('2.0h')
  })

  it('renders days to one decimal place at or beyond 24 hours', () => {
    expect(formatDuration(90000)).toBe('1.0d')
  })
})
