import { useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import toast from 'react-hot-toast'
import { AlertTriangle, DollarSign, PackageCheck, TrendingDown } from 'lucide-react'
import { intelligenceApi } from '../api/intelligence'
import { apiErrorMessage } from '../lib/api'
import { formatCurrency, formatNumber } from '../lib/format'
import { perWeek, recommendationPresentation, reorderByLabel } from '../lib/recommendation'
import { Badge, Button, Card, EmptyState, PageSpinner } from '../components/ui'
import type { Recommendation, RecommendationType, RecommendationsSummary } from '../types'

type Filter = 'all' | RecommendationType

const filters: Array<{ key: Filter; label: string }> = [
  { key: 'all', label: 'All' },
  { key: 'reorder', label: 'Reorder' },
  { key: 'overstock', label: 'Overstock' },
  { key: 'healthy', label: 'Healthy' },
]

function Kpi({
  icon,
  label,
  value,
  tone,
}: {
  icon: ReactNode
  label: string
  value: string
  tone: string
}) {
  return (
    <Card className="flex items-center gap-4 p-5">
      <span className={`flex h-11 w-11 items-center justify-center rounded-lg ${tone}`}>{icon}</span>
      <div>
        <p className="text-sm text-slate-500">{label}</p>
        <p className="text-xl font-semibold text-slate-900">{value}</p>
      </div>
    </Card>
  )
}

function daysLeftLabel(rec: Recommendation): string {
  if (rec.days_of_stock_left === null) return 'No sales'
  return `${Math.round(rec.days_of_stock_left)} days`
}

export function RecommendationsPage() {
  const [summary, setSummary] = useState<RecommendationsSummary | null>(null)
  const [loading, setLoading] = useState(true)
  const [filter, setFilter] = useState<Filter>('reorder')

  useEffect(() => {
    intelligenceApi
      .recommendations()
      .then(setSummary)
      .catch((error) => toast.error(apiErrorMessage(error)))
      .finally(() => setLoading(false))
  }, [])

  const visible = useMemo(() => {
    if (!summary) return []
    if (filter === 'all') return summary.recommendations
    return summary.recommendations.filter((r) => r.type === filter)
  }, [summary, filter])

  if (loading) return <PageSpinner />
  if (!summary) {
    return <EmptyState title="No recommendations" message="Could not load inventory intelligence." />
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-slate-900">Recommendations</h1>
        <p className="text-sm text-slate-500">
          Reorder &amp; overstock guidance from the last {summary.velocity_window_days} days of sales.
          Lead time is assumed to be {summary.default_lead_time_days} days (no per-product lead-time field yet).
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Kpi
          icon={<TrendingDown className="h-5 w-5 text-red-600" />}
          label="Need reorder"
          value={formatNumber(summary.reorder_count)}
          tone="bg-red-100"
        />
        <Kpi
          icon={<AlertTriangle className="h-5 w-5 text-amber-600" />}
          label="Overstocked"
          value={formatNumber(summary.overstock_count)}
          tone="bg-amber-100"
        />
        <Kpi
          icon={<DollarSign className="h-5 w-5 text-indigo-600" />}
          label="Cash tied up in excess"
          value={formatCurrency(summary.total_cash_tied_up)}
          tone="bg-indigo-100"
        />
      </div>

      <div className="flex flex-wrap gap-2">
        {filters.map((f) => {
          const count =
            f.key === 'all'
              ? summary.recommendations.length
              : summary.recommendations.filter((r) => r.type === f.key).length
          return (
            <Button
              key={f.key}
              size="sm"
              variant={filter === f.key ? 'primary' : 'secondary'}
              onClick={() => setFilter(f.key)}
            >
              {f.label} ({count})
            </Button>
          )
        })}
      </div>

      {visible.length === 0 ? (
        <EmptyState title="Nothing here" message="No products match this filter." />
      ) : (
        <Card>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th className="px-5 py-3 font-medium">Product</th>
                  <th className="px-5 py-3 font-medium">Verdict</th>
                  <th className="px-5 py-3 text-right font-medium">On hand</th>
                  <th className="px-5 py-3 text-right font-medium">Sales/wk</th>
                  <th className="px-5 py-3 text-right font-medium">Cover</th>
                  <th className="px-5 py-3 text-right font-medium">Order</th>
                  <th className="px-5 py-3 font-medium">By</th>
                  <th className="px-5 py-3 text-right font-medium">Cash tied up</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {visible.map((rec) => {
                  const present = recommendationPresentation(rec.type)
                  return (
                    <tr key={rec.product_id} className="align-top hover:bg-slate-50">
                      <td className="px-5 py-3">
                        <Link
                          to={`/products/${rec.product_id}`}
                          className="font-medium text-indigo-600 hover:underline"
                        >
                          {rec.name}
                        </Link>
                        <div className="text-xs text-slate-400">
                          {rec.sku}
                          {!rec.is_active && ' · inactive'}
                        </div>
                        <p className="mt-1 max-w-md text-xs text-slate-500">{rec.reasoning}</p>
                      </td>
                      <td className="px-5 py-3">
                        <Badge tone={present.tone}>{present.label}</Badge>
                      </td>
                      <td className="px-5 py-3 text-right text-slate-600">{formatNumber(rec.current_stock)}</td>
                      <td className="px-5 py-3 text-right text-slate-600">{perWeek(rec.sales_velocity)}</td>
                      <td className="px-5 py-3 text-right text-slate-600">{daysLeftLabel(rec)}</td>
                      <td className="px-5 py-3 text-right text-slate-600">
                        {rec.needs_reorder ? formatNumber(rec.suggested_reorder_qty) : '—'}
                      </td>
                      <td className="px-5 py-3">
                        {rec.needs_reorder ? (
                          <span className={rec.is_urgent ? 'font-medium text-red-600' : 'text-slate-600'}>
                            {reorderByLabel(rec)}
                          </span>
                        ) : (
                          <span className="text-slate-400">—</span>
                        )}
                      </td>
                      <td className="px-5 py-3 text-right text-slate-600">
                        {rec.cash_tied_up > 0 ? (
                          <span className="inline-flex items-center gap-1">
                            <PackageCheck className="h-3.5 w-3.5 text-slate-300" />
                            {formatCurrency(rec.cash_tied_up)}
                          </span>
                        ) : (
                          '—'
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </div>
  )
}
