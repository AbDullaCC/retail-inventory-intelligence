import { api } from '../lib/api'
import type { ApiItem, ApiList, Category } from '../types'

export interface CategoryPayload {
  name: string
  description?: string | null
}

export const categoriesApi = {
  list: (): Promise<Category[]> =>
    api.get<ApiList<Category>>('/categories').then((r) => r.data.data),

  get: (id: number): Promise<Category> =>
    api.get<ApiItem<Category>>(`/categories/${id}`).then((r) => r.data.data),

  create: (payload: CategoryPayload): Promise<Category> =>
    api.post<ApiItem<Category>>('/categories', payload).then((r) => r.data.data),

  update: (id: number, payload: CategoryPayload): Promise<Category> =>
    api.put<ApiItem<Category>>(`/categories/${id}`, payload).then((r) => r.data.data),

  remove: (id: number): Promise<void> =>
    api.delete(`/categories/${id}`).then(() => undefined),
}
