import { lazy, Suspense, useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import toast from 'react-hot-toast'
import {
  AlertTriangle,
  Archive,
  CheckCircle2,
  Download,
  Info,
  PackagePlus,
  RefreshCw,
  Search,
  TrendingDown,
  TrendingUp,
} from 'lucide-react'
import { forecastApi } from '../api/forecast'
import { intelligenceApi } from '../api/intelligence'
import { apiErrorMessage } from '../lib/api'
import { formatCurrency, formatDelta, formatNumber, formatShortDate } from '../lib/format'
import { coverDistribution, upcomingStockouts } from '../lib/insights'
import {
  compareBySeverity,
  perWeek,
  perWeekLabel,
  recommendationPresentation,
  reorderByLabel,
} from '../lib/recommendation'
import { usePageTitle } from '../lib/usePageTitle'
import {
  Badge,
  Button,
  Card,
  ChartSkeleton,
  cn,
  EmptyState,
  Input,
  PageHeader,
  Pagination,
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
const CoverDistributionChart = lazy(() => import('../components/charts/CoverDistributionChart'))
const UpcomingStockoutsChart = lazy(() => import('../components/charts/UpcomingStockoutsChart'))

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

const shelfTones: Record<RecommendationType, string> = {
  reorder: 'bg-danger-600',
  overstock: 'bg-warning-600',
  dead_stock: 'bg-slate-400',
  healthy: 'bg-success-600',
}

/**
 * The shelf — the whole catalogue as one proportional strip, one sliver per
 * product, in the verdict colours. Segments toggle the same filters as the
 * KPI cards; the strip shows what the counts can't: proportion.
 */
function ShelfStrip({
  summary,
  filter,
  onToggle,
}: {
  summary: RecommendationsSummary
  filter: Filter
  onToggle: (type: RecommendationType) => void
}) {
  const total = summary.recommendations.length
  if (total === 0) return null

  const segments: Array<{ type: RecommendationType; count: number }> = [
    { type: 'reorder', count: summary.reorder_count },
    { type: 'overstock', count: summary.overstock_count },
    { type: 'dead_stock', count: summary.dead_stock_count },
    { type: 'healthy', count: summary.healthy_count },
  ]

  return (
    <div className="flex h-2.5 origin-left gap-0.5 overflow-hidden rounded-full motion-safe:animate-shelf-in">
      {segments
        .filter((segment) => segment.count > 0)
        .map((segment) => {
          const { label } = recommendationPresentation(segment.type)
          const pct = Math.round((segment.count / total) * 100)
          return (
            <button
              key={segment.type}
              type="button"
              // flex-grow by count keeps the widths honestly proportional.
              style={{ flexGrow: segment.count }}
              title={`${label}: ${formatNumber(segment.count)} products (${pct}%) — click to focus`}
              aria-label={`${label}: ${formatNumber(segment.count)} products, ${pct}% of catalogue`}
              aria-pressed={filter === segment.type}
              onClick={() => onToggle(segment.type)}
              className={cn(
                'h-full min-w-1.5 basis-0 transition-opacity',
                shelfTones[segment.type],
                filter !== 'all' && filter !== segment.type && 'opacity-30 hover:opacity-70',
              )}
            />
          )
        })}
    </div>
  )
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
  // Extreme swings (tiny baselines) read as noise — cap the display at ±500%.
  const label = Math.abs(pct) > 500 ? `${up ? '+' : '−'}500%+` : formatDelta(pct)
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 text-xs font-medium tabular-nums',
        up ? 'text-success-700' : 'text-danger-700',
      )}
    >
      {up ? <TrendingUp className="h-3.5 w-3.5" /> : <TrendingDown className="h-3.5 w-3.5" />}
      {label}
    </span>
  )
}

function csvEscape(value: string | number | null | undefined): string {
  const s = value === null || value === undefined ? '' : String(value)
  return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s
}

const riskLabels: Record<StockoutRisk, string> = {
  high: 'High risk',
  medium: 'Medium risk',
  low: 'Low risk',
}

function RiskBadge({ risk }: { risk: StockoutRisk }) {
  return <Badge tone={riskTones[risk]}>{riskLabels[risk]}</Badge>
}

function coverLabel(rec: Recommendation): string {
  if (rec.days_of_stock_left === null) return 'No sales'
  return `${Math.round(rec.days_of_stock_left)}d`
}

const URGENT_PREVIEW = 5
const PAGE_SIZE = 25

export function RecommendationsPage() {
  usePageTitle('Recommendations')
  const navigate = useNavigate()

  const [summary, setSummary] = useState<RecommendationsSummary | null>(null)
  const [loading, setLoading] = useState(true)
  const [forecasting, setForecasting] = useState(false)
  const [filter, setFilter] = useState<Filter>('reorder')
  const [query, setQuery] = useState('')
  const [sort, setSort] = useState<{ key: SortKey; dir: SortDir } | null>(null)
  const [page, setPage] = useState(1)

  // Any change to what the table shows starts back at page 1.
  useEffect(() => {
    setPage(1)
  }, [filter, query, sort])

  const load = useCallback(
    () =>
      intelligenceApi
        .recommendations()
        .then(setSummary)
        .catch((error) => toast.error(apiErrorMessage(error)))
        .finally(() => setLoading(false)),
    [],
  )

  useEffect(() => {
    void load()
  }, [load])

  const onRefreshForecasts = async () => {
    setForecasting(true)
    try {
      const result = await forecastApi.run()
      toast.success(`Forecasted ${formatNumber(result.forecasted)} products.`)
      await load()
    } catch (error) {
      toast.error(apiErrorMessage(error, 'Forecast refresh failed — is the forecast service running?'))
    } finally {
      setForecasting(false)
    }
  }

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

  // Client-side pages: the API returns the full analysis in one payload (the
  // aggregates need it anyway) — pagination only bounds what the DOM renders.
  const pageCount = Math.max(1, Math.ceil(rows.length / PAGE_SIZE))
  const safePage = Math.min(page, pageCount)
  const pagedRows = rows.slice((safePage - 1) * PAGE_SIZE, safePage * PAGE_SIZE)

  // Soonest stockout first — the 5-row preview must show the MOST urgent items.
  const urgent = useMemo(
    () => (summary ? summary.recommendations.filter((r) => r.is_urgent).sort(compareBySeverity) : []),
    [summary],
  )

  /** This week's whole order plan (all reorder verdicts, independent of search). */
  const orderPlan = useMemo(() => {
    if (!summary) return null
    const items = summary.recommendations.filter((r) => r.type === 'reorder')
    if (items.length === 0) return null
    const units = items.reduce((sum, r) => sum + r.suggested_reorder_qty, 0)
    const cost = items.reduce((sum, r) => sum + r.suggested_reorder_qty * r.unit_cost, 0)
    const hasRevenue = items.some((r) => r.projected_revenue_30d !== null)
    const revenue = items.reduce((sum, r) => sum + (r.projected_revenue_30d ?? 0), 0)
    return {
      items,
      units,
      cost,
      costIsPartial: items.some((r) => r.unit_cost_is_default),
      revenue: hasRevenue ? revenue : null,
    }
  }, [summary])

  const exportOrderPlan = () => {
    if (!orderPlan) return
    const header = [
      'SKU', 'Product', 'Category', 'On hand', 'Expected sales/week', 'Days of cover',
      'Suggested order qty', 'Order by', 'Unit cost', 'Est. cost', 'Stockout risk', 'Urgent',
    ]
    const lines = [...orderPlan.items].sort(compareBySeverity).map((r) =>
      [
        r.sku,
        r.name,
        r.category_name ?? '',
        r.current_stock,
        perWeek(r.sales_velocity),
        r.days_of_stock_left === null ? '' : Math.round(r.days_of_stock_left),
        r.suggested_reorder_qty,
        r.reorder_by_date ?? 'ASAP',
        r.unit_cost.toFixed(2),
        (r.suggested_reorder_qty * r.unit_cost).toFixed(2),
        r.stockout_risk ?? '',
        r.is_urgent ? 'yes' : 'no',
      ]
        .map(csvEscape)
        .join(','),
    )
    // Leading BOM (U+FEFF) so Excel opens it as UTF-8.
    const bom = String.fromCharCode(0xfeff)
    const blob = new Blob([bom + [header.join(','), ...lines].join('\n')], {
      type: 'text/csv;charset=utf-8',
    })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `shelfwise-order-plan-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  }

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

  // Insight-panel data. The stockout timeline only counts reorder rows
  // (other verdicts have no near-term dates); the cover histogram tracks
  // whatever the list currently shows, search included.
  const stockoutDays = useMemo(
    () => upcomingStockouts(filter === 'reorder' ? rows : [], summary?.default_lead_time_days ?? 7),
    [rows, filter, summary],
  )
  const coverBuckets = useMemo(() => coverDistribution(rows), [rows])
  const hasStockoutDates = stockoutDays.some((d) => d.count > 0)

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

  // The header opens with the situation, not a mission statement.
  const lockedCash = overstockCash + summary.dead_stock_cash_recoverable
  const laterCount = summary.reorder_count - urgent.length
  const headline = [
    urgent.length > 0
      ? `${formatNumber(urgent.length)} to order today${laterCount > 0 ? `, ${formatNumber(laterCount)} more this week` : ''}`
      : summary.reorder_count > 0
        ? `${formatNumber(summary.reorder_count)} products to order this week`
        : 'Nothing needs ordering right now',
    ...(lockedCash > 0 ? [`${formatCurrency(lockedCash)} locked in overstock and dead stock`] : []),
  ].join(' — ')

  // One persistent insight slot between the KPIs and the table: the content
  // follows the selected verdict, but the card itself never appears or
  // disappears — no layout jump when a KPI filter is clicked.
  const insight: { title: string; subtitle: ReactNode; chart: ReactNode } = (() => {
    if (filter === 'reorder') {
      return {
        title: "This week's order plan",
        subtitle: orderPlan ? (
          <>
            {formatNumber(orderPlan.items.length)} products ·{' '}
            <span className="tabular-nums">{formatNumber(orderPlan.units)}</span> units · est. cost{' '}
            <span className="font-medium tabular-nums">{formatCurrency(orderPlan.cost)}</span>
            {orderPlan.costIsPartial && (
              <Tooltip content="Some products have no unit cost set — the real total will be higher.">
                <span tabIndex={0} className="cursor-help text-slate-500">
                  *
                </span>
              </Tooltip>
            )}
            {orderPlan.revenue !== null && (
              <>
                {' '}
                · protects{' '}
                <span className="font-medium tabular-nums text-success-700">
                  {formatCurrency(orderPlan.revenue)}
                </span>{' '}
                of projected 30-day sales
              </>
            )}
          </>
        ) : (
          'No products need reordering right now.'
        ),
        chart: hasStockoutDates ? (
          <UpcomingStockoutsChart
            days={stockoutDays}
            leadTimeDays={summary.default_lead_time_days}
            height={236}
          />
        ) : (
          // No stockout dates (stale/missing forecasts) — degrade to the
          // cover histogram instead of an empty timeline.
          <CoverDistributionChart buckets={coverBuckets} />
        ),
      }
    }
    if (filter === 'overstock' || filter === 'dead_stock') {
      return {
        title: filter === 'overstock' ? 'Where cash is tied up' : 'Recoverable cash in dead stock',
        subtitle: 'Top products by cash locked in stock — click a bar to open the product',
        chart:
          cashItems.length > 0 ? (
            <CashTiedUpChart items={cashItems} onSelect={(id) => navigate(`/products/${id}`)} />
          ) : (
            <CoverDistributionChart buckets={coverBuckets} />
          ),
      }
    }
    return {
      title: filter === 'healthy' ? 'Cover runway — healthy products' : 'Cover runway',
      subtitle: 'How long current stock lasts at expected demand, for the products listed below',
      chart: <CoverDistributionChart buckets={coverBuckets} />,
    }
  })()

  return (
    <div>
      <PageHeader
        title="Recommendations"
        description={
          <span className="inline-flex flex-wrap items-center gap-1.5">
            {headline} ·{' '}
            {formatNumber(summary.forecasted_count)} of {formatNumber(total)} products forecasted
            <Tooltip
              content={`Lead time defaults to ${summary.default_lead_time_days} days. Products without a fresh forecast fall back to the ${summary.velocity_window_days}-day average.`}
            >
              <Info
                tabIndex={0}
                className="h-3.5 w-3.5 cursor-help text-slate-400"
                aria-label="About forecast defaults"
              />
            </Tooltip>
          </span>
        }
      />

      <div className="space-y-6">
        {summary.forecasted_count === 0 && total > 0 && (
          <Card className="border-l-4 border-l-warning-600">
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
              <div className="flex items-start gap-3">
                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-warning-600" />
                <div>
                  <h2 className="text-sm font-semibold text-slate-900">Forecasts are missing or stale</h2>
                  <p className="mt-0.5 text-xs text-slate-500">
                    Every verdict below is using the {summary.velocity_window_days}-day average fallback —
                    no demand trends, stockout risk or dead-stock detection until forecasts refresh.
                  </p>
                </div>
              </div>
              <Button variant="secondary" onClick={onRefreshForecasts} loading={forecasting}>
                <RefreshCw className="h-4 w-4" />
                {forecasting ? 'Refreshing (takes a few minutes)…' : 'Refresh forecasts'}
              </Button>
            </div>
          </Card>
        )}

        <ShelfStrip summary={summary} filter={filter} onToggle={toggleFilter} />

        {/* KPI cards double as filters — click to focus the list, click again for all. */}
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard
            label="Need reorder"
            value={formatNumber(summary.reorder_count)}
            tone="danger"
            tinted
            icon={<PackagePlus className="h-5 w-5" />}
            hint={urgent.length > 0 ? `${formatNumber(urgent.length)} to order today` : 'none urgent'}
            onClick={() => toggleFilter('reorder')}
            active={filter === 'reorder'}
          />
          <StatCard
            label="Overstocked"
            value={formatNumber(summary.overstock_count)}
            tone="warning"
            tinted
            icon={<AlertTriangle className="h-5 w-5" />}
            hint={`${formatCurrency(overstockCash)} tied up`}
            onClick={() => toggleFilter('overstock')}
            active={filter === 'overstock'}
          />
          <StatCard
            label="Dead stock"
            value={formatNumber(summary.dead_stock_count)}
            tinted
            icon={<Archive className="h-5 w-5" />}
            hint={`${formatCurrency(summary.dead_stock_cash_recoverable)} recoverable`}
            onClick={() => toggleFilter('dead_stock')}
            active={filter === 'dead_stock'}
          />
          <StatCard
            label="Healthy"
            value={formatNumber(summary.healthy_count)}
            tone="success"
            tinted
            icon={<CheckCircle2 className="h-5 w-5" />}
            hint="no action needed"
            onClick={() => toggleFilter('healthy')}
            active={filter === 'healthy'}
          />
        </div>

        {urgent.length > 0 && (
          <Card className="border-l-4 border-l-danger-600">
            <div className="rounded-xl bg-gradient-to-r from-danger-50/80 to-transparent px-5 py-4">
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

        <Card
          title={insight.title}
          subtitle={insight.subtitle}
          actions={
            filter === 'reorder' && orderPlan ? (
              <Button variant="secondary" onClick={exportOrderPlan}>
                <Download className="h-4 w-4" />
                Export CSV
              </Button>
            ) : undefined
          }
        >
          <div key={filter} className="p-4 motion-safe:animate-panel-in">
            <Suspense fallback={<ChartSkeleton height={260} />}>{insight.chart}</Suspense>
          </div>
        </Card>

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
            <>
              <Table>
              <THead>
                {showVerdict && <TH>Verdict</TH>}
                <TH>Product</TH>
                <TH
                  sortable
                  sortDir={dirFor('trend')}
                  onSort={() => toggleSort('trend')}
                  title="Model-expected demand for the next 28 days vs actual sales in the last 28"
                >
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
                {pagedRows.map((rec) => {
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
                          <Badge tone={present.tone} dot>
                            {present.label}
                          </Badge>
                        </TD>
                      )}
                      <TD>
                        <Link
                          to={`/products/${rec.product_id}`}
                          className="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                        >
                          {rec.name}
                        </Link>
                        <div className="font-mono text-xs text-slate-500">
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
                          <span tabIndex={0} className="line-clamp-1 max-w-xs text-xs text-slate-500">
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
                        {perWeekLabel(rec.sales_velocity)}
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
              {rows.length > PAGE_SIZE && (
                <Pagination
                  meta={{
                    total: rows.length,
                    per_page: PAGE_SIZE,
                    current_page: safePage,
                    last_page: pageCount,
                    from: (safePage - 1) * PAGE_SIZE + 1,
                    to: Math.min(safePage * PAGE_SIZE, rows.length),
                  }}
                  onPage={setPage}
                />
              )}
            </>
          )}
        </Card>
      </div>
    </div>
  )
}
