import { Badge } from './ui'
import type { Product } from '../types'

export function StockStatusBadge({ product }: { product: Product }) {
  if (product.quantity <= 0) {
    return <Badge tone="red">Out of stock</Badge>
  }
  // Driven by the product's optional manual minimum (reorder_level) — the
  // demand-based reorder alerts live on the dashboard and Recommendations page.
  if (product.is_low_stock) {
    return <Badge tone="amber">Below min</Badge>
  }
  return <Badge tone="green">In stock</Badge>
}
