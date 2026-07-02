import type { MovementTrendPoint } from '../types'

/**
 * Percent change of the window's second half vs its first half — the delta
 * chip on KPI cards. Null when the first half has no volume to compare
 * against (a delta would be meaningless or infinite).
 */
export function computeDelta(values: number[]): number | null {
  if (values.length < 4) return null

  const mid = Math.floor(values.length / 2)
  const first = values.slice(0, mid).reduce((sum, v) => sum + v, 0)
  const second = values.slice(mid).reduce((sum, v) => sum + v, 0)

  if (first <= 0) return null
  return ((second - first) / first) * 100
}

/** Units-sold series for sparklines. */
export function toSparkline(series: MovementTrendPoint[]): number[] {
  return series.map((point) => point.units_out)
}

/** Total units sold across the window. */
export function totalUnitsOut(series: MovementTrendPoint[]): number {
  return series.reduce((sum, point) => sum + point.units_out, 0)
}
