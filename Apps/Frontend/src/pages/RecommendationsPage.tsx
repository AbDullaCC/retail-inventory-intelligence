import { lazy, Suspense, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import toast from 'react-hot-toast'
import {
  AlertTriangle,
  Archive,
  CheckCircle2,
  Info,
  Search,
  TrendingDown,
  TrendingUp,
} from 'lucide-react'
import { intelligenceApi } from '../api/intelligence'
import { apiErrorMessage } from '../lib/api'
import { formatCurrency, formatDelta, formatNumber, formatShortDate } from '../lib/format'
import {
  compareBySeverity,
  perWeek,
  recommendationPresentation,
  reorderByLabel,
} from '../lib/recommendation'
import { usePageTitle } from '../lib/usePageTitle'
import {
  Badge,
  Card,
  ChartSkeleton,
  cn,
  EmptyState,
  Input,
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
import type { SegmentedOption, SortDir } from '../components/ui'
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

type SortKey = 'trend' | 'stock' | 'velocity' | 'cover' | 'order' | 'cash'

function sortValue(rec: Recommendation, key: SortKey): number | null {
  switch (key) {
    case 'trend':
      return rec.demand_trend_pct
    case 'stock':
      return rec.current_stock
    case 'velocity':
      return rec.sales_velocity
    case 'cover':
      return rec.days_of_stock_left
    case 'order':
      return rec.needs_reorder ? rec.suggested_reorder_qty : null
    case 'cash':
      return rec.cash_tied_up > 0 ? rec.cash_tied_up : null
  }
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

const URGENT_PREVIEW = 5

export function RecommendationsPage() {
  usePageTitle('Recommendations')
  const navigate = useNavigate()

  const [summary, setSummary] = useState<RecommendationsSummary | null>(null)
  const [loading, setLoading] = useState(true)
  const [filter, setFilter] = useState<Filter>('reorder')
  const [query, setQuery] = useState('')
  const [sort, setSort] = useState<{ key: SortKey; dir: SortDir } | null>(null)

  useEffect(() => {
    intelligenceApi
      .recommendations()
      .then(setSummary)
      .catch((error) => toast.error(apiErrorMessage(error)))
      .finally(() => setLoading(false))
  }, [])

  /** Clicking a KPI card applies its filter; clicking it again shows everything. */
  const toggleFilter = (next: RecommendationType) => {
    setFilter((current) => (current === next ? 'all' : next))
  }

  /** asc → desc (cover starts asc: shortest cover is the alarming one), third click back to severity order. */
  const toggleSort = (key: SortKey) => {
    setSort((current) => {
      const first: SortDir = key === 'cover' ? 'asc' : 'desc'
      if (!current || current.key !== key) return { key, dir: first }
      if (current.dir === first) return { key, dir: first === 'asc' ? 'desc' : 'asc' }
      return null
    })
  }

  const rows = useMemo(() => {
    if (!summary) return []
    let list = summary.recommendations
    if (filter !== 'all') list = list.filter((r) => r.type === filter)
    const q = query.trim().toLowerCase()
    if (q) {
      list = list.filter(
        (r) =>
          r.name.toLowerCase().includes(q) ||
          r.sku.toLowerCase().includes(q) ||
          (r.category_name?.toLowerCase().includes(q) ?? false),
      )
    }
    const sorted = [...list]
    if (sort) {
      const { key, dir } = sort
      sorted.sort((a, b) => {
        const av = sortValue(a, key)
        const bv = sortValue(b, key)
        if (av === null && bv === null) return 0
        if (av === null) return 1
        if (bv === null) return -1
        return dir === 'asc' ? av - bv : bv - av
      })
    } else {
      sorted.sort(compareBySeverity)
    }
    return sorted
  }, [summary, filter, query, sort])

  const urgent = useMemo(
    () => (summary ? summary.recommendations.filter((r) => r.is_urgent) : []),
    [summary],
  )

  const overstockCash = useMemo(
    () =>
      summary
        ? summary.recommendations
            .filter((r) => r.type === 'overstock')
            .reduce((sum, r) => sum + r.cash_tied_up, 0)
        : 0,
    [summary],
  )

  const cashItems = useMemo(
    () =>
      rows
        .filter((r) => r.cash_tied_up > 0)
        .sort((a, b) => b.cash_tied_up - a.cash_tied_up)
        .slice(0, 8)
        .map((r) => ({ productId: r.product_id, name: r.name, value: r.cash_tied_up })),
    [rows],
  )

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

  const showVerdict = filter === 'all'
  const showReorderCols = filter === 'all' || filter === 'reorder'
  const showCashCol = filter === 'all' || filter === 'overstock' || filter === 'dead_stock'

  const dirFor = (key: SortKey): SortDir | null => (sort?.key === key ? sort.dir : null)

  return (
    <div>
      <PageHeader
        title="Recommendations"
        description={
          <span className="inline-flex flex-wrap items-center gap-1.5">
            What to order, what to trim, and where cash is stuck ·{' '}
            {formatNumber(summary.forecasted_count)} of {formatNumber(total)} products forecasted
            <Tooltip
              content={`Lead time defaults to ${summary.default_lead_time_days} days. Products without a fresh forecast fall back to the ${summary.velocity_window_days}-day average.`}
            >
              <Info className="h-3.5 w-3.5 cursor-help text-slate-400" aria-label="About forecast defaults" />
            </Tooltip>
          </span>
        }
      />

      <div className="space-y-6">
        {/* KPI cards double as filters — click to focus the list, click again for all. */}
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard
            label="Need reorder"
            value={formatNumber(summary.reorder_count)}
            tone="danger"
            icon={<TrendingDown className="h-5 w-5" />}
            hint={urgent.length > 0 ? `${formatNumber(urgent.length)} to order today` : 'none urgent'}
            onClick={() => toggleFilter('reorder')}
            active={filter === 'reorder'}
          />
          <StatCard
            label="Overstocked"
            value={formatNumber(summary.overstock_count)}
            tone="warning"
            icon={<AlertTriangle className="h-5 w-5" />}
            hint={`${formatCurrency(overstockCash)} tied up`}
            onClick={() => toggleFilter('overstock')}
            active={filter === 'overstock'}
          />
          <StatCard
            label="Dead stock"
            value={formatNumber(summary.dead_stock_count)}
            icon={<Archive className="h-5 w-5" />}
            hint={`${formatCurrency(summary.dead_stock_cash_recoverable)} recoverable`}
            onClick={() => toggleFilter('dead_stock')}
            active={filter === 'dead_stock'}
          />
          <StatCard
            label="Healthy"
            value={formatNumber(summary.healthy_count)}
            tone="success"
            icon={<CheckCircle2 className="h-5 w-5" />}
            hint="no action needed"
            onClick={() => toggleFilter('healthy')}
            active={filter === 'healthy'}
          />
        </div>

        {urgent.length > 0 && (
          <Card className="border-l-4 border-l-danger-600">
            <div className="px-5 py-4">
              <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <span className="inline-flex items-center gap-2">
                  <AlertTriangle className="h-4 w-4 text-danger-600" />
                  <h2 className="text-sm font-semibold text-slate-900">Order today</h2>
                </span>
                <span className="text-xs text-slate-500">
                  {urgent.length === 1
                    ? 'this product runs'
                    : `these ${formatNumber(urgent.length)} products run`}{' '}
                  out before new stock could arrive
                </span>
              </div>
              <ul className="mt-1 divide-y divide-slate-100">
                {urgent.slice(0, URGENT_PREVIEW).map((rec) => (
                  <li
                    key={rec.product_id}
                    className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2.5"
                  >
                    <div className="min-w-0">
                      <Link
                        to={`/products/${rec.product_id}`}
                        className="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                      >
                        {rec.name}
                      </Link>
                      <span className="ml-2 font-mono text-xs text-slate-400">{rec.sku}</span>
                    </div>
                    <div className="flex items-center gap-4">
                      {rec.projected_stockout_date && (
                        <span className="text-xs text-slate-500">
                          runs out ~{formatShortDate(rec.projected_stockout_date)}
                        </span>
                      )}
                      <span className="text-sm text-slate-700">
                        Order{' '}
                        <span className="font-semibold tabular-nums">
                          {formatNumber(rec.suggested_reorder_qty)}
                        </span>{' '}
                        units
                      </span>
                      {rec.stockout_risk && <RiskBadge risk={rec.stockout_risk} />}
                    </div>
                  </li>
                ))}
              </ul>
              {urgent.length > URGENT_PREVIEW && (
                <button
                  type="button"
                  onClick={() => setFilter('reorder')}
                  className="mt-2 text-xs font-medium text-brand-700 hover:underline"
                >
                  Show all {formatNumber(urgent.length)} urgent products in the list below
                </button>
              )}
            </div>
          </Card>
        )}

        {(filter === 'overstock' || filter === 'dead_stock') && cashItems.length > 0 && (
          <Card
            title={filter === 'overstock' ? 'Where cash is tied up' : 'Recoverable cash in dead stock'}
            subtitle="Top products by cash locked in stock — click a bar to open the product"
          >
            <div className="p-4">
              <Suspense fallback={<ChartSkeleton height={260} />}>
                <CashTiedUpChart items={cashItems} onSelect={(id) => navigate(`/products/${id}`)} />
              </Suspense>
            </div>
          </Card>
        )}

        <Card>
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
            <SegmentedControl options={filterOptions} value={filter} onChange={setFilter} />
            <div className="relative w-64 max-w-full">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <Input
                className="pl-9"
                placeholder="Search name, SKU or category…"
                aria-label="Search recommendations"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
              />
            </div>
          </div>

          {rows.length === 0 ? (
            <EmptyState
              title="Nothing here"
              message={query ? `No products match “${query}”.` : emptyCopy[filter]}
            />
          ) : (
            <Table>
              <THead>
                {showVerdict && <TH>Verdict</TH>}
                <TH>Product</TH>
                <TH sortable sortDir={dirFor('trend')} onSort={() => toggleSort('trend')}>
                  Trend
                </TH>
                {showReorderCols && <TH>Risk</TH>}
                <TH align="right" sortable sortDir={dirFor('stock')} onSort={() => toggleSort('stock')}>
                  On hand
                </TH>
                <TH
                  align="right"
                  sortable
                  sortDir={dirFor('velocity')}
                  onSort={() => toggleSort('velocity')}
                >
                  Sales/wk
                </TH>
                <TH align="right" sortable sortDir={dirFor('cover')} onSort={() => toggleSort('cover')}>
                  Cover
                </TH>
                {showReorderCols && (
                  <TH align="right" sortable sortDir={dirFor('order')} onSort={() => toggleSort('order')}>
                    Order
                  </TH>
                )}
                {showReorderCols && <TH>By</TH>}
                {showCashCol && (
                  <TH align="right" sortable sortDir={dirFor('cash')} onSort={() => toggleSort('cash')}>
                    Cash tied up
                  </TH>
                )}
              </THead>
              <TBody>
                {rows.map((rec) => {
                  const present = recommendationPresentation(rec.type)
                  const coverDanger =
                    rec.days_of_stock_left !== null && rec.days_of_stock_left < rec.lead_time_days
                  const sourceLabel =
                    rec.forecast_source === 'model'
                      ? `Forecast model: ${rec.model_used ?? 'model'}`
                      : `${summary.velocity_window_days}-day average (no fresh forecast)`
                  return (
                    <tr
                      key={rec.product_id}
                      onClick={() => navigate(`/products/${rec.product_id}`)}
                      className={cn(
                        'cursor-pointer border-l-2 align-top transition-colors hover:bg-slate-50',
                        rowTint[rec.type],
                      )}
                    >
                      {showVerdict && (
                        <TD>
                          <Badge tone={present.tone}>{present.label}</Badge>
                        </TD>
                      )}
                      <TD>
                        <Link
                          to={`/products/${rec.product_id}`}
                          className="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                        >
                          {rec.name}
                        </Link>
                        <div className="font-mono text-xs text-slate-400">
                          {rec.sku}
                          {rec.category_name && ` · ${rec.category_name}`}
                          {!rec.is_active && ' · inactive'}
                        </div>
                        <Tooltip
                          content={
                            <>
                              {rec.reasoning}
                              <span className="mt-1 block text-slate-400">{sourceLabel}</span>
                            </>
                          }
                          className="mt-1"
                        >
                          <span className="line-clamp-1 max-w-xs text-xs text-slate-400">
                            {rec.reasoning}
                          </span>
                        </Tooltip>
                      </TD>
                      <TD>
                        <TrendChip pct={rec.demand_trend_pct} />
                      </TD>
                      {showReorderCols && (
                        <TD>
                          {rec.stockout_risk ? (
                            <RiskBadge risk={rec.stockout_risk} />
                          ) : (
                            <span className="text-slate-400">—</span>
                          )}
                        </TD>
                      )}
                      <TD numeric className="text-slate-600">
                        {formatNumber(rec.current_stock)}
                      </TD>
                      <TD numeric className="text-slate-600">
                        {formatNumber(perWeek(rec.sales_velocity))}
                      </TD>
                      <TD numeric className={coverDanger ? 'text-danger-600' : 'text-slate-600'}>
                        {coverLabel(rec)}
                      </TD>
                      {showReorderCols && (
                        <TD numeric className="text-slate-600">
                          {rec.needs_reorder ? formatNumber(rec.suggested_reorder_qty) : '—'}
                        </TD>
                      )}
                      {showReorderCols && (
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
                      )}
                      {showCashCol && (
                        <TD numeric className="text-slate-600">
                          {rec.cash_tied_up > 0 ? formatCurrency(rec.cash_tied_up) : '—'}
                        </TD>
                      )}
                    </tr>
                  )
                })}
              </TBody>
            </Table>
          )}
        </Card>
      </div>
    </div>
  )
}
