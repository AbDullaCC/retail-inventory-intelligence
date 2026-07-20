import { describe, expect, it } from 'vitest'
import {
  compareBySeverity,
  perWeek,
  perWeekLabel,
  recommendationPresentation,
  reorderByLabel,
} from './recommendation'
import type { Recommendation } from '../types'

describe('recommendationPresentation', () => {
  it('maps each verdict to a label and tone', () => {
    expect(recommendationPresentation('reorder')).toEqual({ label: 'Reorder', tone: 'red' })
    expect(recommendationPresentation('overstock')).toEqual({ label: 'Overstock', tone: 'amber' })
    expect(recommendationPresentation('dead_stock')).toEqual({ label: 'Dead stock', tone: 'gray' })
    expect(recommendationPresentation('healthy')).toEqual({ label: 'Healthy', tone: 'green' })
  })
})

describe('perWeek', () => {
  it('rounds the daily velocity to a weekly figure', () => {
    expect(perWeek(4)).toBe(28)
    expect(perWeek(0.5)).toBe(4) // 3.5 → 4
    expect(perWeek(0)).toBe(0)
  })
})

describe('perWeekLabel', () => {
  it('shows "<1" for slow movers instead of a misleading zero', () => {
    expect(perWeekLabel(0.05)).toBe('<1')
    expect(perWeekLabel(0)).toBe('0')
    expect(perWeekLabel(4)).toBe('28')
  })
})

describe('reorderByLabel', () => {
  it('shows "Today" when urgent', () => {
    expect(reorderByLabel({ reorder_by_date: '2026-07-01', is_urgent: true })).toBe('Today')
  })

  it('shows a short human date when not urgent', () => {
    expect(reorderByLabel({ reorder_by_date: '2026-07-20', is_urgent: false })).toBe('Jul 20')
  })

  it('shows a dash when there is no date', () => {
    expect(reorderByLabel({ reorder_by_date: null, is_urgent: false })).toBe('—')
  })
})

describe('compareBySeverity', () => {
  const rec = (
    overrides: Partial<
      Pick<Recommendation, 'is_urgent' | 'type' | 'days_of_stock_left' | 'cash_tied_up'>
    >,
  ) => ({
    is_urgent: false,
    type: 'healthy' as const,
    days_of_stock_left: null,
    cash_tied_up: 0,
    ...overrides,
  })

  it('puts urgent items before everything else', () => {
    const urgent = rec({ is_urgent: true, type: 'reorder' })
    const calm = rec({ type: 'reorder' })
    expect(compareBySeverity(urgent, calm)).toBeLessThan(0)
    expect(compareBySeverity(calm, urgent)).toBeGreaterThan(0)
  })

  it('orders verdicts reorder → overstock → dead stock → healthy', () => {
    const sorted = [
      rec({ type: 'healthy' }),
      rec({ type: 'reorder' }),
      rec({ type: 'dead_stock' }),
      rec({ type: 'overstock' }),
    ].sort(compareBySeverity)
    expect(sorted.map((r) => r.type)).toEqual(['reorder', 'overstock', 'dead_stock', 'healthy'])
  })

  it('sorts reorders by shortest cover, treating null cover as last', () => {
    const sorted = [
      rec({ type: 'reorder', days_of_stock_left: null }),
      rec({ type: 'reorder', days_of_stock_left: 9 }),
      rec({ type: 'reorder', days_of_stock_left: 2 }),
    ].sort(compareBySeverity)
    expect(sorted.map((r) => r.days_of_stock_left)).toEqual([2, 9, null])
  })

  it('sorts cash verdicts by most cash tied up', () => {
    const sorted = [
      rec({ type: 'overstock', cash_tied_up: 50 }),
      rec({ type: 'overstock', cash_tied_up: 400 }),
    ].sort(compareBySeverity)
    expect(sorted.map((r) => r.cash_tied_up)).toEqual([400, 50])
  })
})
