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

export type RecommendationType = 'reorder' | 'overstock' | 'healthy' | 'dead_stock'

export type ForecastSource = 'model' | 'fallback'

export type StockoutRisk = 'high' | 'medium' | 'low'

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
  forecast_source: ForecastSource
  model_used: string | null
  forecast_generated_at: string | null
  projected_stockout_date: string | null
  stockout_risk: StockoutRisk | null
  demand_trend_pct: number | null
  projected_units_30d: number | null
  projected_revenue_30d: number | null
}

export interface RecommendationsSummary {
  reorder_count: number
  overstock_count: number
  healthy_count: number
  dead_stock_count: number
  dead_stock_cash_recoverable: number
  forecasted_count: number
  projected_revenue_30d: number | null
  total_cash_tied_up: number
  velocity_window_days: number
  default_lead_time_days: number
  generated_at: string
  recommendations: Recommendation[]
}

export interface DemandHistoryPoint {
  date: string
  qty: number
}

export interface ForecastPoint {
  date: string
  mean: number
  lo_90: number | null
  hi_90: number | null
}

export interface ProductForecast {
  product_id: number
  sku: string
  name: string
  generated_at: string | null
  model_used: string | null
  horizon_days: number | null
  history: DemandHistoryPoint[]
  forecast: ForecastPoint[]
}

export interface ForecastSummaryDaily {
  date: string
  mean: number
  hi_90: number
}

export interface ForecastSummary {
  forecasted_count: number
  projected_units_30d: number
  projected_revenue_30d: number
  model_mix: Record<string, number>
  generated_at: string | null
  daily: ForecastSummaryDaily[]
}

export interface MovementTrendPoint {
  date: string
  units_in: number
  units_out: number
  movements: number
}

export interface CategoryValue {
  category_id: number
  category_name: string
  stock_value: number
  units: number
}

export interface DashboardTrends {
  days: number
  series: MovementTrendPoint[]
  category_values: CategoryValue[]
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
