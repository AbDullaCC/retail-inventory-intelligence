import { lazy, Suspense, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import toast from 'react-hot-toast'
import { AlertTriangle, Archive, Info, TrendingDown, TrendingUp, Wallet } from 'lucide-react'
import { intelligenceApi } from '../api/intelligence'
import { apiErrorMessage } from '../lib/api'
import { formatCurrency, formatDelta, formatNumber, formatShortDate } from '../lib/format'
import { perWeek, recommendationPresentation, reorderByLabel } from '../lib/recommendation'
import { usePageTitle } from '../lib/usePageTitle'
import {
  Badge,
  Card,
  ChartSkeleton,
  cn,
  EmptyState,
  PageHeader,
  SegmentedControl,
  StatCard,
  StatCardSkeleton,
  Table,
  TableSkeleton,
  TBody,
  TD,
  TH,
  THead,
  Tooltip,
} from '../components/ui'
import type { SegmentedOption } from '../components/ui'
import type {
  Recommendation,
  RecommendationType,
  RecommendationsSummary,
  StockoutRisk,
} from '../types'

const CashTiedUpChart = lazy(() => import('../components/charts/CashTiedUpChart'))

type Filter = 'all' | RecommendationType

const emptyCopy: Record<Filter, string> = {
  all: 'No recommendations yet — record some stock movements first.',
  reorder: 'No products need reordering right now.',
  overstock: 'No products are overstocked.',
  dead_stock: 'No dead stock — everything has moved recently.',
  healthy: 'No products fall in the healthy band right now.',
}

const riskTones: Record<StockoutRisk, 'red' | 'amber' | 'green'> = {
  high: 'red',
  medium: 'amber',
  low: 'green',
}

/** Left-edge row tint per verdict — keep in sync with recommendationPresentation tones. */
const rowTint: Record<RecommendationType, string> = {
  reorder: 'border-l-danger-600',
  overstock: 'border-l-warning-600',
  dead_stock: 'border-l-slate-400',
  healthy: 'border-l-success-600',
}

function TrendChip({ pct }: { pct: number | null }) {
  if (pct === null) return <span className="text-slate-400">—</span>
  const up = pct >= 0
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 text-xs font-medium tabular-nums',
        up ? 'text-success-700' : 'text-danger-700',
      )}
    >
      {up ? <TrendingUp className="h-3.5 w-3.5" /> : <TrendingDown className="h-3.5 w-3.5" />}
      {formatDelta(pct)}
    </span>
  )
}

function RiskBadge({ risk }: { risk: StockoutRisk }) {
  return <Badge tone={riskTones[risk]}>{risk} risk</Badge>
}

function coverLabel(rec: Recommendation): string {
  if (rec.days_of_stock_left === null) return 'No sales'
  return `${Math.round(rec.days_of_stock_left)}d`
}

export function RecommendationsPage() {
  usePageTitle('Recommendations')
  const navigate = useNavigate()

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

  const urgent = useMemo(
    () => (summary ? summary.recommendations.filter((r) => r.is_urgent) : []),
    [summary],
  )

  const cashItems = useMemo(() => {
    if (!summary) return []
    return [...summary.recommendations]
      .filter((r) => r.cash_tied_up > 0)
      .sort((a, b) => b.cash_tied_up - a.cash_tied_up)
      .slice(0, 8)
      .map((r) => ({ productId: r.product_id, name: r.name, value: r.cash_tied_up }))
  }, [summary])

  if (loading) {
    return (
      <div>
        <PageHeader title="Recommendations" description="Model-driven demand guidance" />
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {Array.from({ length: 4 }, (_, i) => (
              <StatCardSkeleton key={i} />
            ))}
          </div>
          <Card>
            <TableSkeleton rows={8} cols={8} />
          </Card>
        </div>
      </div>
    )
  }

  if (!summary) {
    return (
      <div>
        <PageHeader title="Recommendations" description="Model-driven demand guidance" />
        <Card>
          <EmptyState title="No recommendations" message="Could not load inventory intelligence." />
        </Card>
      </div>
    )
  }

  const total = summary.recommendations.length

  const filterOptions: Array<SegmentedOption<Filter>> = [
    { value: 'all', label: 'All', count: total },
    { value: 'reorder', label: 'Reorder', count: summary.reorder_count },
    { value: 'overstock', label: 'Overstock', count: summary.overstock_count },
    { value: 'dead_stock', label: 'Dead stock', count: summary.dead_stock_count },
    { value: 'healthy', label: 'Healthy', count: summary.healthy_count },
  ]

  return (
    <div>
      <PageHeader
        title="Recommendations"
        description={
          <span className="inline-flex flex-wrap items-center gap-1.5">
            Model-driven demand guidance · {formatNumber(summary.forecasted_count)} of{' '}
            {formatNumber(total)} products forecasted
            <Tooltip
              content={`Lead time defaults to ${summary.default_lead_time_days} days. Products without a fresh forecast fall back to the ${summary.velocity_window_days}-day average.`}
            >
              <Info className="h-3.5 w-3.5 cursor-help text-slate-400" aria-label="About forecast defaults" />
            </Tooltip>
          </span>
        }
      />

      <div className="space-y-6">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard
            label="Need reorder"
            value={formatNumber(summary.reorder_count)}
            tone="danger"
            icon={<TrendingDown className="h-5 w-5" />}
          />
          <StatCard
            label="Overstocked"
            value={formatNumber(summary.overstock_count)}
            tone="warning"
            icon={<AlertTriangle className="h-5 w-5" />}
          />
          <StatCard
            label="Dead stock"
            value={formatNumber(summary.dead_stock_count)}
            icon={<Archive className="h-5 w-5" />}
            hint={`${formatCurrency(summary.dead_stock_cash_recoverable)} recoverable`}
          />
          <StatCard
            label="Cash tied up"
            value={formatCurrency(summary.total_cash_tied_up)}
            icon={<Wallet className="h-5 w-5" />}
          />
        </div>

        {urgent.length > 0 && (
          <section className="space-y-3">
            <h2 className="text-sm font-semibold text-slate-900">Order today</h2>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              {urgent.map((rec) => (
                <Card key={rec.product_id} className="border-l-4 border-l-danger-600 p-4">
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <Link
                        to={`/products/${rec.product_id}`}
                        className="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                      >
                        {rec.name}
                      </Link>
                      <p className="font-mono text-xs text-slate-400">{rec.sku}</p>
                    </div>
                    {rec.stockout_risk && <RiskBadge risk={rec.stockout_risk} />}
                  </div>
                  <p className="mt-2 text-sm text-slate-700">
                    Order <span className="font-bold">{formatNumber(rec.suggested_reorder_qty)}</span>{' '}
                    units today
                  </p>
                  {rec.projected_stockout_date && (
                    <p className="mt-0.5 text-xs text-slate-500">
                      runs out ~{formatShortDate(rec.projected_stockout_date)}
                    </p>
                  )}
                </Card>
              ))}
            </div>
          </section>
        )}

        <SegmentedControl options={filterOptions} value={filter} onChange={setFilter} />

        {summary.overstock_count > 0 && cashItems.length > 0 && (
          <Card
            title="Where cash is tied up"
            subtitle="Top products by cash locked in excess stock — click a bar to open the product"
          >
            <div className="p-4">
              <Suspense fallback={<ChartSkeleton height={260} />}>
                <CashTiedUpChart items={cashItems} onSelect={(id) => navigate(`/products/${id}`)} />
              </Suspense>
            </div>
          </Card>
        )}

        {visible.length === 0 ? (
          <Card>
            <EmptyState title="Nothing here" message={emptyCopy[filter]} />
          </Card>
        ) : (
          <Card>
            <Table>
              <THead>
                <TH>Verdict</TH>
                <TH>Product</TH>
                <TH>Trend</TH>
                <TH>Risk</TH>
                <TH align="right">On hand</TH>
                <TH align="right">Sales/wk</TH>
                <TH align="right">Cover</TH>
                <TH align="right">Order</TH>
                <TH>By</TH>
                <TH align="right">Cash tied up</TH>
              </THead>
              <TBody>
                {visible.map((rec) => {
                  const present = recommendationPresentation(rec.type)
                  const coverDanger =
                    rec.days_of_stock_left !== null && rec.days_of_stock_left < rec.lead_time_days
                  return (
                    <tr
                      key={rec.product_id}
                      className={cn(
                        'border-l-2 align-top transition-colors hover:bg-slate-50',
                        rowTint[rec.type],
                      )}
                    >
                      <TD>
                        <Badge tone={present.tone}>{present.label}</Badge>
                        <p className="mt-1 text-[10px] text-slate-400">
                          {rec.forecast_source === 'model' ? rec.model_used ?? 'model' : 'window-avg'}
                        </p>
                      </TD>
                      <TD>
                        <Link
                          to={`/products/${rec.product_id}`}
                          className="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                        >
                          {rec.name}
                        </Link>
                        <div className="font-mono text-xs text-slate-400">
                          {rec.sku}
                          {!rec.is_active && ' · inactive'}
                        </div>
                        <Tooltip content={rec.reasoning} className="mt-1">
                          <span className="line-clamp-1 max-w-xs text-xs text-slate-400">
                            {rec.reasoning}
                          </span>
                        </Tooltip>
                      </TD>
                      <TD>
                        <TrendChip pct={rec.demand_trend_pct} />
                      </TD>
                      <TD>
                        {rec.stockout_risk ? (
                          <RiskBadge risk={rec.stockout_risk} />
                        ) : (
                          <span className="text-slate-400">—</span>
                        )}
                      </TD>
                      <TD numeric className="text-slate-600">
                        {formatNumber(rec.current_stock)}
                      </TD>
                      <TD numeric className="text-slate-600">
                        {formatNumber(perWeek(rec.sales_velocity))}
                      </TD>
                      <TD numeric className={coverDanger ? 'text-danger-600' : 'text-slate-600'}>
                        {coverLabel(rec)}
                      </TD>
                      <TD numeric className="text-slate-600">
                        {rec.needs_reorder ? formatNumber(rec.suggested_reorder_qty) : '—'}
                      </TD>
                      <TD>
                        {rec.needs_reorder ? (
                          <span
                            className={cn(
                              rec.is_urgent ? 'font-medium text-danger-600' : 'text-slate-600',
                            )}
                          >
                            {reorderByLabel(rec)}
                          </span>
                        ) : (
                          <span className="text-slate-400">—</span>
                        )}
                      </TD>
                      <TD numeric className="text-slate-600">
                        {rec.cash_tied_up > 0 ? formatCurrency(rec.cash_tied_up) : '—'}
                      </TD>
                    </tr>
                  )
                })}
              </TBody>
            </Table>
          </Card>
        )}
      </div>
    </div>
  )
}
