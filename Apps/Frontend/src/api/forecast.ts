import { api } from '../lib/api'
import type { ApiItem, ForecastRunSummary, ForecastSummary, ProductForecast } from '../types'

export const forecastApi = {
  forProduct: (id: number): Promise<ProductForecast> =>
    api.get<ApiItem<ProductForecast>>(`/products/${id}/forecast`).then((r) => r.data.data),

  summary: (): Promise<ForecastSummary> =>
    api.get<ApiItem<ForecastSummary>>('/forecast/summary').then((r) => r.data.data),

  run: (): Promise<ForecastRunSummary> =>
    api.post<ApiItem<ForecastRunSummary>>('/forecast/run').then((r) => r.data.data),
}
