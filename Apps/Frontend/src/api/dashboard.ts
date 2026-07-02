import { api } from '../lib/api'
import type { ApiItem, DashboardSummary, DashboardTrends } from '../types'

export const dashboardApi = {
  summary: (): Promise<DashboardSummary> =>
    api.get<ApiItem<DashboardSummary>>('/dashboard/summary').then((r) => r.data.data),

  trends: (params?: { days?: number; product_id?: number }): Promise<DashboardTrends> =>
    api.get<ApiItem<DashboardTrends>>('/dashboard/trends', { params }).then((r) => r.data.data),
}
