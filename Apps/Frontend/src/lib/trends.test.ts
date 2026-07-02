import { describe, it, expect } from 'vitest'
import { computeDelta, toSparkline, totalUnitsOut } from './trends'
import type { MovementTrendPoint } from '../types'

const point = (date: string, units_out: number, units_in = 0): MovementTrendPoint => ({
  date,
  units_out,
  units_in,
  movements: units_out > 0 || units_in > 0 ? 1 : 0,
})

describe('computeDelta', () => {
  it('compares the second half against the first', () => {
    // First half 10+10=20, second half 20+20=40 → +100%.
    expect(computeDelta([10, 10, 20, 20])).toBe(100)
  })

  it('handles declines', () => {
    expect(computeDelta([20, 20, 10, 10])).toBe(-50)
  })

  it('returns null when there is nothing to compare against', () => {
    expect(computeDelta([0, 0, 5, 5])).toBeNull()
    expect(computeDelta([1, 2])).toBeNull()
    expect(computeDelta([])).toBeNull()
  })

  it('puts the odd middle element in the second half', () => {
    // mid = 2: [10, 10] vs [10, 20, 30] → +200%.
    expect(computeDelta([10, 10, 10, 20, 30])).toBe(200)
  })
})

describe('toSparkline / totalUnitsOut', () => {
  const series = [point('2026-06-01', 4), point('2026-06-02', 0), point('2026-06-03', 7)]

  it('extracts the units-sold curve', () => {
    expect(toSparkline(series)).toEqual([4, 0, 7])
  })

  it('sums units sold', () => {
    expect(totalUnitsOut(series)).toBe(11)
  })
})
