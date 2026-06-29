import { useCallback, useEffect, useState } from 'react'
import type { FormEvent, ReactNode } from 'react'
import { Link, useParams } from 'react-router-dom'
import toast from 'react-hot-toast'
import { ArrowLeft } from 'lucide-react'
import { productsApi } from '../api/products'
import { stockApi } from '../api/stock'
import { apiErrorMessage } from '../lib/api'
import {
  Badge,
  Button,
  Card,
  EmptyState,
  Field,
  Input,
  PageSpinner,
  Pagination,
  Select,
} from '../components/ui'
import { StockStatusBadge } from '../components/StockStatusBadge'
import { formatCurrency, formatDateTime, formatNumber } from '../lib/format'
import type { ApiPaginated, Product, StockMovement, StockMovementType } from '../types'

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex items-center justify-between py-2">
      <span className="text-sm text-slate-500">{label}</span>
      <span className="text-sm font-medium text-slate-800">{value}</span>
    </div>
  )
}

function movementTone(type: StockMovementType): 'green' | 'red' | 'indigo' {
  if (type === 'in') return 'green'
  if (type === 'out') return 'red'
  return 'indigo'
}

const typeHints: Record<StockMovementType, string> = {
  in: 'Add this many units to stock.',
  out: 'Remove this many units from stock.',
  adjustment: 'Set the on-hand quantity to this exact value.',
}

export function ProductDetailPage() {
  const { id } = useParams<{ id: string }>()
  const productId = Number(id)

  const [product, setProduct] = useState<Product | null>(null)
  const [history, setHistory] = useState<ApiPaginated<StockMovement> | null>(null)
  const [loading, setLoading] = useState(true)

  const [type, setType] = useState<StockMovementType>('in')
  const [quantity, setQuantity] = useState('1')
  const [reason, setReason] = useState('')
  const [adjusting, setAdjusting] = useState(false)

  const loadProduct = useCallback(() => {
    return productsApi.get(productId).then(setProduct)
  }, [productId])

  const loadHistory = useCallback(
    (page: number) => {
      return stockApi.history(productId, page).then((res) => {
        setHistory(res)
      })
    },
    [productId],
  )

  useEffect(() => {
    if (Number.isNaN(productId)) {
      setLoading(false)
      return
    }
    Promise.all([loadProduct(), loadHistory(1)])
      .catch((error) => toast.error(apiErrorMessage(error)))
      .finally(() => setLoading(false))
  }, [productId, loadProduct, loadHistory])

  const onAdjust = async (e: FormEvent) => {
    e.preventDefault()
    setAdjusting(true)
    try {
      await stockApi.adjust(productId, {
        type,
        quantity: Number(quantity || '0'),
        reason: reason || null,
      })
      toast.success('Stock updated.')
      setQuantity(type === 'adjustment' ? quantity : '1')
      setReason('')
      await Promise.all([loadProduct(), loadHistory(1)])
    } catch (error) {
      toast.error(apiErrorMessage(error))
    } finally {
      setAdjusting(false)
    }
  }

  if (loading) return <PageSpinner />
  if (!product) {
    return (
      <EmptyState
        title="Product not found"
        message="It may have been removed."
        action={
          <Link to="/products" className="text-sm font-medium text-indigo-600 hover:underline">
            Back to products
          </Link>
        }
      />
    )
  }

  return (
    <div className="space-y-6">
      <Link
        to="/products"
        className="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-800"
      >
        <ArrowLeft className="h-4 w-4" />
        Back to products
      </Link>

      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">{product.name}</h1>
          <p className="text-sm text-slate-500">
            {product.sku} · {product.category?.name ?? 'Uncategorised'}
          </p>
        </div>
        <StockStatusBadge product={product} />
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Details */}
        <Card className="p-5 lg:col-span-2">
          <h2 className="mb-2 font-semibold text-slate-900">Details</h2>
          {product.description && <p className="mb-3 text-sm text-slate-500">{product.description}</p>}
          <div className="divide-y divide-slate-100">
            <DetailRow label="On hand" value={`${formatNumber(product.quantity)} units`} />
            <DetailRow label="Reorder level" value={formatNumber(product.reorder_level)} />
            <DetailRow label="Price" value={formatCurrency(product.price)} />
            <DetailRow label="Cost" value={formatCurrency(product.cost)} />
            <DetailRow label="Stock value" value={formatCurrency(product.stock_value)} />
            <DetailRow
              label="Status"
              value={product.is_active ? <Badge tone="green">Active</Badge> : <Badge tone="gray">Inactive</Badge>}
            />
          </div>
        </Card>

        {/* Adjust stock */}
        <Card className="p-5">
          <h2 className="mb-3 font-semibold text-slate-900">Adjust stock</h2>
          <form onSubmit={onAdjust} className="space-y-3">
            <Field label="Movement type" htmlFor="adj-type">
              <Select
                id="adj-type"
                value={type}
                onChange={(e) => setType(e.target.value as StockMovementType)}
              >
                <option value="in">Stock in</option>
                <option value="out">Stock out</option>
                <option value="adjustment">Set exact quantity</option>
              </Select>
            </Field>
            <Field label="Quantity" htmlFor="adj-qty" hint={typeHints[type]} required>
              <Input
                id="adj-qty"
                type="number"
                min="0"
                value={quantity}
                onChange={(e) => setQuantity(e.target.value)}
                required
              />
            </Field>
            <Field label="Reason" htmlFor="adj-reason">
              <Input
                id="adj-reason"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="e.g. Restock, Sale, Recount"
              />
            </Field>
            <Button type="submit" className="w-full" loading={adjusting}>
              Apply adjustment
            </Button>
          </form>
        </Card>
      </div>

      {/* History */}
      <Card>
        <div className="border-b border-slate-200 px-5 py-4">
          <h2 className="font-semibold text-slate-900">Movement history</h2>
        </div>
        {!history || history.data.length === 0 ? (
          <EmptyState title="No movements yet" message="Stock changes will be logged here." />
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
                  <tr>
                    <th className="px-5 py-3 font-medium">When</th>
                    <th className="px-5 py-3 font-medium">Type</th>
                    <th className="px-5 py-3 text-right font-medium">Change</th>
                    <th className="px-5 py-3 text-right font-medium">Before → After</th>
                    <th className="px-5 py-3 font-medium">Reason</th>
                    <th className="px-5 py-3 font-medium">By</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {history.data.map((m) => (
                    <tr key={m.id} className="hover:bg-slate-50">
                      <td className="px-5 py-3 text-slate-500">{formatDateTime(m.created_at)}</td>
                      <td className="px-5 py-3">
                        <Badge tone={movementTone(m.type)}>{m.type_label}</Badge>
                      </td>
                      <td
                        className={
                          m.change >= 0
                            ? 'px-5 py-3 text-right font-medium text-emerald-600'
                            : 'px-5 py-3 text-right font-medium text-red-600'
                        }
                      >
                        {m.change >= 0 ? '+' : ''}
                        {m.change}
                      </td>
                      <td className="px-5 py-3 text-right text-slate-600">
                        {m.quantity_before} → {m.quantity_after}
                      </td>
                      <td className="px-5 py-3 text-slate-500">{m.reason ?? '—'}</td>
                      <td className="px-5 py-3 text-slate-500">{m.user_name ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pagination meta={history.meta} onPage={(page) => void loadHistory(page)} />
          </>
        )}
      </Card>
    </div>
  )
}
