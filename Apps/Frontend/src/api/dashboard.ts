import { api } from '../lib/api'
import type { ApiItem, DashboardSummary } from '../types'

export const dashboardApi = {
  summary: (): Promise<DashboardSummary> =>
    api.get<ApiItem<DashboardSummary>>('/dashboard/summary').then((r) => r.data.data),
}
