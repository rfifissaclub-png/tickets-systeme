import { act, renderHook } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'

import { useDebouncedValue } from './use-debounced-value'

describe('useDebouncedValue', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('keeps the previous value until the delay expires', () => {
    vi.useFakeTimers()
    const { result, rerender } = renderHook(({ value }) => useDebouncedValue(value, 300), {
      initialProps: { value: 'initial' },
    })

    rerender({ value: 'updated' })

    act(() => {
      vi.advanceTimersByTime(299)
    })
    expect(result.current).toBe('initial')

    act(() => {
      vi.advanceTimersByTime(1)
    })
    expect(result.current).toBe('updated')
  })

  it('cancels a stale update when the value changes again', () => {
    vi.useFakeTimers()
    const { result, rerender } = renderHook(({ value }) => useDebouncedValue(value, 300), {
      initialProps: { value: 'initial' },
    })

    rerender({ value: 'first update' })
    act(() => {
      vi.advanceTimersByTime(200)
    })
    rerender({ value: 'latest update' })

    act(() => {
      vi.advanceTimersByTime(299)
    })
    expect(result.current).toBe('initial')

    act(() => {
      vi.advanceTimersByTime(1)
    })
    expect(result.current).toBe('latest update')
  })

  it('applies the updated value when the delay is zero', () => {
    vi.useFakeTimers()
    const { result, rerender } = renderHook(({ value }) => useDebouncedValue(value, 0), {
      initialProps: { value: 1 },
    })

    rerender({ value: 2 })
    act(() => {
      vi.runOnlyPendingTimers()
    })

    expect(result.current).toBe(2)
  })
})