import { describe, expect, it } from 'vitest'
import { perWeek, recommendationPresentation, reorderByLabel } from './recommendation'

describe('recommendationPresentation', () => {
  it('maps each verdict to a label and tone', () => {
    expect(recommendationPresentation('reorder')).toEqual({ label: 'Reorder', tone: 'red' })
    expect(recommendationPresentation('overstock')).toEqual({ label: 'Overstock', tone: 'amber' })
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

describe('reorderByLabel', () => {
  it('shows "Today" when urgent', () => {
    expect(reorderByLabel({ reorder_by_date: '2026-07-01', is_urgent: true })).toBe('Today')
  })

  it('shows the date when not urgent', () => {
    expect(reorderByLabel({ reorder_by_date: '2026-07-20', is_urgent: false })).toBe('2026-07-20')
  })

  it('shows a dash when there is no date', () => {
    expect(reorderByLabel({ reorder_by_date: null, is_urgent: false })).toBe('—')
  })
})
