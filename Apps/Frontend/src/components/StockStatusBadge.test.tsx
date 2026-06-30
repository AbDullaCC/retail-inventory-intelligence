import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { StockStatusBadge } from './StockStatusBadge'
import type { Product } from '../types'

function makeProduct(overrides: Partial<Product>): Product {
  return {
    id: 1,
    category_id: 1,
    sku: 'SKU-1',
    name: 'Test Product',
    description: null,
    price: 1,
    cost: null,
    quantity: 50,
    reorder_level: 10,
    is_active: true,
    is_low_stock: false,
    stock_value: 50,
    category: null,
    created_at: null,
    updated_at: null,
    ...overrides,
  }
}

describe('StockStatusBadge', () => {
  it('shows "Out of stock" when quantity is zero', () => {
    render(<StockStatusBadge product={makeProduct({ quantity: 0 })} />)
    expect(screen.getByText('Out of stock')).toBeInTheDocument()
  })

  it('shows "Low stock" when flagged and quantity > 0', () => {
    render(<StockStatusBadge product={makeProduct({ quantity: 5, is_low_stock: true })} />)
    expect(screen.getByText('Low stock')).toBeInTheDocument()
  })

  it('shows "In stock" otherwise', () => {
    render(<StockStatusBadge product={makeProduct({ quantity: 80, is_low_stock: false })} />)
    expect(screen.getByText('In stock')).toBeInTheDocument()
  })
})
