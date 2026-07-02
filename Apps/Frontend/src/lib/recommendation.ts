import type { Recommendation, RecommendationType } from '../types'

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

/** Human label for the reorder-by date ("Today" when urgent). */
export function reorderByLabel(rec: Pick<Recommendation, 'reorder_by_date' | 'is_urgent'>): string {
  if (!rec.reorder_by_date) return '—'
  return rec.is_urgent ? 'Today' : rec.reorder_by_date
}
