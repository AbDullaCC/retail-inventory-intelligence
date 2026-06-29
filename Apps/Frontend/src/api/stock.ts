import { api } from '../lib/api'
import type { ApiItem, ApiList, ApiPaginated, StockMovement, StockMovementType } from '../types'

export interface StockAdjustmentPayload {
  type: StockMovementType
  quantity: number
  reason?: string | null
}

export const stockApi = {
  adjust: (productId: number, payload: StockAdjustmentPayload): Promise<StockMovement> =>
    api
      .post<ApiItem<StockMovement>>(`/products/${productId}/stock-adjustments`, payload)
      .then((r) => r.data.data),

  history: (productId: number, page = 1, perPage = 15): Promise<ApiPaginated<StockMovement>> =>
    api
      .get<ApiPaginated<StockMovement>>(`/products/${productId}/stock-movements`, {
        params: { page, per_page: perPage },
      })
      .then((r) => r.data),

  recent: (limit = 20): Promise<StockMovement[]> =>
    api.get<ApiList<StockMovement>>('/stock-movements', { params: { limit } }).then((r) => r.data.data),
}
