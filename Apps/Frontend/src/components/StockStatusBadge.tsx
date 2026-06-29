import { Badge } from './ui'
import type { Product } from '../types'

export function StockStatusBadge({ product }: { product: Product }) {
  if (product.quantity <= 0) {
    return <Badge tone="red">Out of stock</Badge>
  }
  if (product.is_low_stock) {
    return <Badge tone="amber">Low stock</Badge>
  }
  return <Badge tone="green">In stock</Badge>
}
