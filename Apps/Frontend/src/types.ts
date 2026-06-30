// API contract types — mirror the Laravel DTOs (snake_case JSON keys).

export interface ApiItem<T> {
  data: T
  message?: string
}

export interface ApiList<T> {
  data: T[]
  message?: string
}

export interface PaginationMeta {
  total: number
  per_page: number
  current_page: number
  last_page: number
  from: number | null
  to: number | null
}

export interface ApiPaginated<T> {
  data: T[]
  meta: PaginationMeta
}

export interface User {
  id: number
  name: string
  email: string
  created_at: string | null
}

export interface AuthToken {
  token: string
  token_type: string
  user: User
}

export interface Category {
  id: number
  name: string
  description: string | null
  products_count: number | null
  created_at: string | null
  updated_at: string | null
}

export interface Product {
  id: number
  category_id: number
  sku: string
  name: string
  description: string | null
  price: number
  cost: number | null
  quantity: number
  reorder_level: number
  is_active: boolean
  is_low_stock: boolean
  stock_value: number
  category: Category | null
  created_at: string | null
  updated_at: string | null
}

export type StockMovementType = 'in' | 'out' | 'adjustment'

export interface StockMovement {
  id: number
  product_id: number
  product_name: string | null
  type: StockMovementType
  type_label: string
  quantity: number
  quantity_before: number
  quantity_after: number
  change: number
  reason: string | null
  user_id: number | null
  user_name: string | null
  created_at: string | null
}

export type RecommendationType = 'reorder' | 'overstock' | 'healthy'

export interface Recommendation {
  product_id: number
  sku: string
  name: string
  category_name: string | null
  is_active: boolean
  type: RecommendationType
  current_stock: number
  sales_velocity: number
  days_of_stock_left: number | null
  lead_time_days: number
  lead_time_is_default: boolean
  unit_cost: number
  unit_cost_is_default: boolean
  needs_reorder: boolean
  suggested_reorder_qty: number
  reorder_by_date: string | null
  is_urgent: boolean
  is_overstocked: boolean
  cash_tied_up: number
  reasoning: string
}

export interface RecommendationsSummary {
  reorder_count: number
  overstock_count: number
  healthy_count: number
  total_cash_tied_up: number
  velocity_window_days: number
  default_lead_time_days: number
  generated_at: string
  recommendations: Recommendation[]
}

export interface DashboardSummary {
  total_products: number
  active_products: number
  total_categories: number
  low_stock_count: number
  out_of_stock_count: number
  total_stock_units: number
  total_stock_value: number
  low_stock_products: Product[]
  recent_movements: StockMovement[]
}
