import type { Recommendation, RecommendationType } from '../types'
import { formatShortDate } from './format'

type Tone = 'green' | 'red' | 'amber' | 'indigo' | 'gray'

export interface RecommendationPresentation {
  label: string
  tone: Tone
}

/** Badge label + colour for a recommendation verdict. */
export function recommendationPresentation(type: RecommendationType): RecommendationPresentation {
  switch (type) {
    case 'reorder':
      return { label: 'Reorder', tone: 'red' }
    case 'overstock':
      return { label: 'Overstock', tone: 'amber' }
    case 'dead_stock':
      return { label: 'Dead stock', tone: 'gray' }
    default:
      return { label: 'Healthy', tone: 'green' }
  }
}

/** Sales velocity expressed per week, rounded for display only. */
export function perWeek(velocity: number): number {
  return Math.round(velocity * 7)
}

/** Human label for the reorder-by date ("Today" when urgent, "Jul 20" otherwise). */
export function reorderByLabel(rec: Pick<Recommendation, 'reorder_by_date' | 'is_urgent'>): string {
  if (!rec.reorder_by_date) return '—'
  return rec.is_urgent ? 'Today' : formatShortDate(rec.reorder_by_date)
}

const TYPE_RANK: Record<RecommendationType, number> = {
  reorder: 0,
  overstock: 1,
  dead_stock: 2,
  healthy: 3,
}

type SeverityFields = Pick<
  Recommendation,
  'is_urgent' | 'type' | 'days_of_stock_left' | 'cash_tied_up'
>

/**
 * Default table order: urgent reorders first, then reorder → overstock →
 * dead stock → healthy. Within reorders the shortest cover wins; within the
 * cash verdicts the most cash tied up wins.
 */
export function compareBySeverity(a: SeverityFields, b: SeverityFields): number {
  const urgency = Number(b.is_urgent) - Number(a.is_urgent)
  if (urgency !== 0) return urgency
  const rank = TYPE_RANK[a.type] - TYPE_RANK[b.type]
  if (rank !== 0) return rank
  if (a.type === 'reorder') {
    return (
      (a.days_of_stock_left ?? Number.POSITIVE_INFINITY) -
      (b.days_of_stock_left ?? Number.POSITIVE_INFINITY)
    )
  }
  return b.cash_tied_up - a.cash_tied_up
}
