import { describe, expect, it } from 'vitest'
import { coverDistribution, upcomingStockouts } from './insights'

describe('upcomingStockouts', () => {
  const today = new Date(2026, 6, 20) // Jul 20, local

  it('zero-fills the window and flags days inside the lead time', () => {
    const days = upcomingStockouts(
      [
        { name: 'A', projected_stockout_date: '2026-07-20' },
        { name: 'B', projected_stockout_date: '2026-07-22' },
        { name: 'C', projected_stockout_date: '2026-07-22' },
        { name: 'D', projected_stockout_date: null },
      ],
      7,
      21,
      today,
    )

    expect(days).toHaveLength(21)
    expect(days[0]).toMatchObject({ date: '2026-07-20', count: 1, withinLeadTime: true })
    expect(days[1].count).toBe(0)
    expect(days[2]).toMatchObject({ date: '2026-07-22', count: 2 })
    expect(days[6].withinLeadTime).toBe(true)
    expect(days[7].withinLeadTime).toBe(false)
  })

  it('caps tooltip names at three but keeps the full count', () => {
    const recs = ['A', 'B', 'C', 'D'].map((name) => ({
      name,
      projected_stockout_date: '2026-07-21',
    }))

    const days = upcomingStockouts(recs, 7, 21, today)

    expect(days[1].count).toBe(4)
    expect(days[1].names).toEqual(['A', 'B', 'C'])
  })

  it('ignores dates outside the window', () => {
    const days = upcomingStockouts(
      [{ name: 'X', projected_stockout_date: '2026-09-01' }],
      7,
      21,
      today,
    )

    expect(days.every((d) => d.count === 0)).toBe(true)
  })
})

describe('coverDistribution', () => {
  it('assigns products to threshold-aligned buckets', () => {
    const buckets = coverDistribution([
      { days_of_stock_left: 3 },
      { days_of_stock_left: 7 },
      { days_of_stock_left: 7.4 },
      { days_of_stock_left: 14 },
      { days_of_stock_left: 30 },
      { days_of_stock_left: 60 },
      { days_of_stock_left: 200 },
      { days_of_stock_left: null },
    ])

    expect(buckets.map((b) => b.count)).toEqual([2, 2, 1, 1, 1, 1])
    expect(buckets[0].tone).toBe('danger')
    expect(buckets[4].tone).toBe('warning') // 60d+ is overstock territory
    expect(buckets[5]).toMatchObject({ label: 'No sales', tone: 'neutral' })
  })
})
