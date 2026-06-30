import { api } from '../lib/api'
import type { ApiItem, Recommendation, RecommendationsSummary } from '../types'

export const intelligenceApi = {
  recommendations: (): Promise<RecommendationsSummary> =>
    api
      .get<ApiItem<RecommendationsSummary>>('/intelligence/recommendations')
      .then((r) => r.data.data),

  forProduct: (id: number): Promise<Recommendation> =>
    api.get<ApiItem<Recommendation>>(`/products/${id}/recommendation`).then((r) => r.data.data),
}
