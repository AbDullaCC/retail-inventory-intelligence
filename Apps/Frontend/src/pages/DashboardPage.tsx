import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import toast from 'react-hot-toast'
import {
  AlertTriangle,
  Boxes,
  DollarSign,
  Package,
  PackageX,
  Tags,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { dashboardApi } from '../api/dashboard'
import { apiErrorMessage } from '../lib/api'
import { Badge, Card, EmptyState, PageSpinner } from '../components/ui'
import { StockStatusBadge } from '../components/StockStatusBadge'
import { formatCurrency, formatDateTime, formatNumber } from '../lib/format'
import type { DashboardSummary, StockMovementType } from '../types'

function StatCard({
  label,
  value,
  icon: Icon,
  tone,
}: {
  label: string
  value: string
  icon: LucideIcon
  tone: string
}) {
  return (
    <Card className="p-5">
      <div className="flex items-center justify-between">
        <p className="text-sm text-slate-500">{label}</p>
        <span className={`flex h-9 w-9 items-center justify-center rounded-lg ${tone}`}>
          <Icon className="h-5 w-5" />
        </span>
      </div>
      <p className="mt-3 text-2xl font-semibold text-slate-900">{value}</p>
    </Card>
  )
}

function movementTone(type: StockMovementType): 'green' | 'red' | 'indigo' {
  if (type === 'in') return 'green'
  if (type === 'out') return 'red'
  return 'indigo'
}

export function DashboardPage() {
  const [summary, setSummary] = useState<DashboardSummary | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    dashboardApi
      .summary()
      .then(setSummary)
      .catch((error) => toast.error(apiErrorMessage(error)))
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <PageSpinner />
  if (!summary) return <EmptyState title="No data" message="Could not load the dashboard." />

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-slate-900">Dashboard</h1>
        <p className="text-sm text-slate-500">Overview of your inventory health.</p>
      </div>

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
        <StatCard
          label="Products"
          value={`${formatNumber(summary.active_products)} / ${formatNumber(summary.total_products)}`}
          icon={Package}
          tone="bg-indigo-100 text-indigo-700"
        />
        <StatCard
          label="Categories"
          value={formatNumber(summary.total_categories)}
          icon={Tags}
          tone="bg-sky-100 text-sky-700"
        />
        <StatCard
          label="Stock value"
          value={formatCurrency(summary.total_stock_value)}
          icon={DollarSign}
          tone="bg-emerald-100 text-emerald-700"
        />
        <StatCard
          label="Stock units"
          value={formatNumber(summary.total_stock_units)}
          icon={Boxes}
          tone="bg-slate-100 text-slate-700"
        />
        <StatCard
          label="Low stock"
          value={formatNumber(summary.low_stock_count)}
          icon={AlertTriangle}
          tone="bg-amber-100 text-amber-700"
        />
        <StatCard
          label="Out of stock"
          value={formatNumber(summary.out_of_stock_count)}
          icon={PackageX}
          tone="bg-red-100 text-red-700"
        />
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        {/* Low stock products */}
        <Card>
          <div className="border-b border-slate-200 px-5 py-4">
            <h2 className="font-semibold text-slate-900">Low stock alerts</h2>
            <p className="text-sm text-slate-500">Products at or below their reorder level.</p>
          </div>
          {summary.low_stock_products.length === 0 ? (
            <EmptyState title="All good!" message="No products need restocking right now." />
          ) : (
            <ul className="divide-y divide-slate-100">
              {summary.low_stock_products.map((product) => (
                <li key={product.id} className="flex items-center justify-between px-5 py-3">
                  <div className="min-w-0">
                    <Link
                      to={`/products/${product.id}`}
                      className="truncate font-medium text-slate-800 hover:text-indigo-600"
                    >
                      {product.name}
                    </Link>
                    <p className="text-xs text-slate-400">{product.sku}</p>
                  </div>
                  <div className="flex items-center gap-3">
                    <span className="text-sm text-slate-500">
                      {product.quantity} / {product.reorder_level}
                    </span>
                    <StockStatusBadge product={product} />
                  </div>
                </li>
              ))}
            </ul>
          )}
        </Card>

        {/* Recent movements */}
        <Card>
          <div className="border-b border-slate-200 px-5 py-4">
            <h2 className="font-semibold text-slate-900">Recent stock activity</h2>
            <p className="text-sm text-slate-500">Latest inventory movements.</p>
          </div>
          {summary.recent_movements.length === 0 ? (
            <EmptyState title="No activity yet" message="Stock movements will appear here." />
          ) : (
            <ul className="divide-y divide-slate-100">
              {summary.recent_movements.map((movement) => (
                <li key={movement.id} className="flex items-center justify-between px-5 py-3">
                  <div className="min-w-0">
                    <p className="truncate font-medium text-slate-800">
                      {movement.product_name ?? `Product #${movement.product_id}`}
                    </p>
                    <p className="text-xs text-slate-400">
                      {movement.reason ?? movement.type_label} · {formatDateTime(movement.created_at)}
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    <span
                      className={
                        movement.change >= 0
                          ? 'text-sm font-medium text-emerald-600'
                          : 'text-sm font-medium text-red-600'
                      }
                    >
                      {movement.change >= 0 ? '+' : ''}
                      {movement.change}
                    </span>
                    <Badge tone={movementTone(movement.type)}>{movement.type_label}</Badge>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>
    </div>
  )
}
