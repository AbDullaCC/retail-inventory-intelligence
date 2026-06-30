import { api } from '../lib/api'
import type { ApiItem, ApiPaginated, Product } from '../types'

export interface ProductFilters {
  search?: string
  category_id?: number | ''
  low_stock?: boolean
  is_active?: boolean | ''
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface ProductPayload {
  category_id: number
  sku: string
  name: string
  description?: string | null
  price: number
  cost?: number | null
  reorder_level: number
  is_active: boolean
  quantity?: number
}

export function cleanParams(filters: ProductFilters): Record<string, string | number> {
  const params: Record<string, string | number> = {}
  for (const [key, value] of Object.entries(filters)) {
    if (value === undefined || value === '' || value === false) continue
    params[key] = value === true ? 'true' : (value as string | number)
  }
  return params
}

export const productsApi = {
  list: (filters: ProductFilters = {}): Promise<ApiPaginated<Product>> =>
    api.get<ApiPaginated<Product>>('/products', { params: cleanParams(filters) }).then((r) => r.data),

  get: (id: number): Promise<Product> =>
    api.get<ApiItem<Product>>(`/products/${id}`).then((r) => r.data.data),

  create: (payload: ProductPayload): Promise<Product> =>
    api.post<ApiItem<Product>>('/products', payload).then((r) => r.data.data),

  update: (id: number, payload: ProductPayload): Promise<Product> =>
    api.put<ApiItem<Product>>(`/products/${id}`, payload).then((r) => r.data.data),

  remove: (id: number): Promise<void> =>
    api.delete(`/products/${id}`).then(() => undefined),
}
