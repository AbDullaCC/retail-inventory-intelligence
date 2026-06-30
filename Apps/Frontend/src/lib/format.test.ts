import { describe, it, expect } from 'vitest'
import { formatCurrency, formatDateTime, formatNumber } from './format'

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
