import { api } from '../lib/api'
import type { ApiItem, ShopifyStatus, ShopifySyncStats } from '../types'

interface SyncResult {
  stats: ShopifySyncStats
  status: ShopifyStatus
}

export const shopifyApi = {
  status: (): Promise<ShopifyStatus> =>
    api.get<ApiItem<ShopifyStatus>>('/shopify/status').then((r) => r.data.data),

  connect: (domain: string, token: string): Promise<ShopifyStatus> =>
    api.post<ApiItem<ShopifyStatus>>('/shopify/connect', { domain, token }).then((r) => r.data.data),

  sync: (): Promise<SyncResult> =>
    api.post<ApiItem<SyncResult>>('/shopify/sync').then((r) => r.data.data),

  disconnect: (): Promise<void> => api.delete('/shopify/connection').then(() => undefined),
}
