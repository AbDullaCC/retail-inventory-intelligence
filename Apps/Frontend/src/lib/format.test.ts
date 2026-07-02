import { describe, it, expect } from 'vitest'
import {
  formatCompactCurrency,
  formatCurrency,
  formatDateTime,
  formatDelta,
  formatNumber,
  formatShortDate,
} from './format'

describe('formatCurrency', () => {
  it('formats a number as USD', () => {
    expect(formatCurrency(1234.5)).toBe('$1,234.50')
  })

  it('returns an em dash for null/undefined', () => {
    expect(formatCurrency(null)).toBe('—')
    expect(formatCurrency(undefined)).toBe('—')
  })
})

describe('formatNumber', () => {
  it('adds thousands separators', () => {
    expect(formatNumber(1000000)).toBe('1,000,000')
  })

  it('returns an em dash for null', () => {
    expect(formatNumber(null)).toBe('—')
  })
})

describe('formatDateTime', () => {
  it('returns an em dash for null or invalid input', () => {
    expect(formatDateTime(null)).toBe('—')
    expect(formatDateTime('not-a-date')).toBe('—')
  })

  it('formats a valid ISO string', () => {
    const out = formatDateTime('2026-06-29T14:30:00Z')
    expect(out).not.toBe('—')
    expect(typeof out).toBe('string')
  })
})

describe('formatCompactCurrency', () => {
  it('compacts thousands', () => {
    expect(formatCompactCurrency(12400)).toBe('$12.4K')
  })

  it('leaves small amounts readable', () => {
    expect(formatCompactCurrency(950)).toBe('$950')
  })

  it('returns an em dash for null', () => {
    expect(formatCompactCurrency(null)).toBe('—')
  })
})

describe('formatDelta', () => {
  it('signs positive deltas', () => {
    expect(formatDelta(12.53)).toBe('+12.5%')
  })

  it('keeps the minus on negative deltas', () => {
    expect(formatDelta(-3.24)).toBe('-3.2%')
  })

  it('handles zero and non-finite values', () => {
    expect(formatDelta(0)).toBe('0%')
    expect(formatDelta(null)).toBe('—')
    expect(formatDelta(Number.POSITIVE_INFINITY)).toBe('—')
  })
})

describe('formatShortDate', () => {
  it('formats a Y-m-d date', () => {
    expect(formatShortDate('2026-06-03')).toBe('Jun 3')
  })

  it('returns an em dash for empty/invalid input', () => {
    expect(formatShortDate(null)).toBe('—')
    expect(formatShortDate('nope')).toBe('—')
  })
})
