import type { Recommendation } from '../types'

/** One day on the stockout timeline. */
export interface StockoutDay {
  /** ISO date (yyyy-mm-dd), local timezone. */
  date: string
  count: number
  /** Up to three product names for the tooltip. */
  names: string[]
  /** True when the day lands inside the lead time — ordering today can no longer prevent it. */
  withinLeadTime: boolean
}

/** Local-timezone yyyy-mm-dd (the API's stockout dates are date-only strings). */
function isoDate(d: Date): string {
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${month}-${day}`
}

/**
 * Products grouped by projected stockout day over the next `windowDays`,
 * zero-filled so the time axis stays continuous. Products without a stockout
 * date (no fresh forecast, or stock outlasting the horizon) are skipped.
 */
export function upcomingStockouts(
  recs: Array<Pick<Recommendation, 'name' | 'projected_stockout_date'>>,
  leadTimeDays: number,
  windowDays = 21,
  today: Date = new Date(),
): StockoutDay[] {
  const byDate = new Map<string, { count: number; names: string[] }>()
  for (const rec of recs) {
    if (!rec.projected_stockout_date) continue
    const entry = byDate.get(rec.projected_stockout_date) ?? { count: 0, names: [] }
    entry.count += 1
    if (entry.names.length < 3) entry.names.push(rec.name)
    byDate.set(rec.projected_stockout_date, entry)
  }

  const start = new Date(today.getFullYear(), today.getMonth(), today.getDate())
  return Array.from({ length: windowDays }, (_, i) => {
    const d = new Date(start)
    d.setDate(start.getDate() + i)
    const key = isoDate(d)
    const entry = byDate.get(key)
    return {
      date: key,
      count: entry?.count ?? 0,
      names: entry?.names ?? [],
      withinLeadTime: i < leadTimeDays,
    }
  })
}

export type CoverTone = 'danger' | 'warning' | 'success' | 'neutral'

export interface CoverBucket {
  label: string
  count: number
  tone: CoverTone
}

/**
 * Days-of-cover histogram. Bucket edges follow the intelligence thresholds:
 * ≤7d sits inside the default lead time, 8–14d is reorder territory, >60d is
 * the overstock threshold; products with no expected demand land in "No sales".
 */
export function coverDistribution(
  recs: Array<Pick<Recommendation, 'days_of_stock_left'>>,
): CoverBucket[] {
  const buckets: CoverBucket[] = [
    { label: '≤ 7d', count: 0, tone: 'danger' },
    { label: '8–14d', count: 0, tone: 'warning' },
    { label: '15–30d', count: 0, tone: 'success' },
    { label: '31–60d', count: 0, tone: 'success' },
    { label: '60d+', count: 0, tone: 'warning' },
    { label: 'No sales', count: 0, tone: 'neutral' },
  ]
  for (const rec of recs) {
    const days = rec.days_of_stock_left
    const idx =
      days === null ? 5 : days <= 7 ? 0 : days <= 14 ? 1 : days <= 30 ? 2 : days <= 60 ? 3 : 4
    buckets[idx].count += 1
  }
  return buckets
}
