import { lazy, Suspense, useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import toast from 'react-hot-toast'
import { AlertTriangle, PackageX, TrendingUp, Wallet } from 'lucide-react'
import { dashboardApi } from '../api/dashboard'
import { forecastApi } from '../api/forecast'
import { apiErrorMessage } from '../lib/api'
import {
  Badge,
  CapacityBar,
  Card,
  ChartSkeleton,
  EmptyState,
  PageHeader,
  SegmentedControl,
  StatCard,
  StatCardSkeleton,
  TableSkeleton,
  cn,
} from '../components/ui'
import { formatCurrency, formatDateTime, formatNumber } from '../lib/format'
import { computeDelta, toSparkline, totalUnitsOut } from '../lib/trends'
import { usePageTitle } from '../lib/usePageTitle'
import type {
  DashboardSummary,
  DashboardTrends,
  ForecastSummary,
  Recommendation,
  StockMovementType,
} from '../types'

const MovementsTrendChart = lazy(() => import('../components/charts/MovementsTrendChart'))
const CategoryValueChart = lazy(() => import('../components/charts/CategoryValueChart'))

type TrendWindow = '7' | '30' | '90'

const TREND_OPTIONS: Array<{ value: TrendWindow; label: string }> = [
  { value: '7', label: '7d' },
  { value: '30', label: '30d' },
  { value: '90', label: '90d' },
]

function movementTone(type: StockMovementType): 'green' | 'red' | 'indigo' {
  if (type === 'in') return 'green'
  if (type === 'out') return 'red'
  return 'indigo'
}

function coverLabel(rec: Recommendation): string {
  if (rec.days_of_stock_left === null) return 'no sales data'
  return `${Math.round(rec.days_of_stock_left)}d cover`
}

function DashboardSkeleton() {
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-4 xl:grid-cols-5">
        {Array.from({ length: 5 }, (_, i) => (
          <StatCardSkeleton key={i} />
        ))}
      </div>
      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <div className="p-5">
            <ChartSkeleton height={260} />
          </div>
        </Card>
        <Card>
          <div className="p-5">
            <ChartSkeleton height={260} />
          </div>
        </Card>
      </div>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <TableSkeleton rows={5} cols={3} />
        </Card>
        <Card>
          <TableSkeleton rows={5} cols={3} />
        </Card>
      </div>
    </div>
  )
}

export function DashboardPage() {
  usePageTitle('Dashboard')

  const navigate = useNavigate()
  const [summary, setSummary] = useState<DashboardSummary | null>(null)
  const [trends, setTrends] = useState<DashboardTrends | null>(null)
  const [forecast, setForecast] = useState<ForecastSummary | null>(null)
  const [loading, setLoading] = useState(true)
  const [trendsLoading, setTrendsLoading] = useState(true)
  const [days, setDays] = useState<TrendWindow>('30')

  useEffect(() => {
    let cancelled = false
    Promise.all([
      dashboardApi
        .summary()
        .then((data) => {
          if (!cancelled) setSummary(data)
        })
        .catch((error) => toast.error(apiErrorMessage(error))),
      forecastApi
        .summary()
        .then((data) => {
          if (!cancelled) setForecast(data)
        })
        // Forecasts are optional — render the dashboard without a projection.
        .catch(() => undefined),
    ]).finally(() => {
      if (!cancelled) setLoading(false)
    })
    return () => {
      cancelled = true
    }
  }, [])

  // Changing the window refetches only the trends.
  useEffect(() => {
    let cancelled = false
    setTrendsLoading(true)
    dashboardApi
      .trends({ days: Number(days) })
      .then((data) => {
        if (!cancelled) setTrends(data)
      })
      .catch((error) => toast.error(apiErrorMessage(error)))
      .finally(() => {
        if (!cancelled) setTrendsLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [days])

  const today = new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  })

  const pageLoading = loading || (trendsLoading && !trends)
  const series = trends?.series ?? []
  const sparkline = toSparkline(series)
  const hasForecast = forecast !== null && forecast.forecasted_count > 0

  // Date plus the one thing that matters right now, not just the date.
  const situation = summary
    ? summary.urgent_count > 0
      ? `${formatNumber(summary.urgent_count)} products need ordering now`
      : summary.reorder_count > 0
        ? `${formatNumber(summary.reorder_count)} products to order this week`
        : 'nothing needs ordering'
    : null

  return (
    <div className="space-y-4">
      <PageHeader
        title="Dashboard"
        description={situation ? `Today is ${today} · ${situation}` : `Today is ${today}`}
        actions={<SegmentedControl options={TREND_OPTIONS} value={days} onChange={setDays} />}
      />

      {pageLoading ? (
        <DashboardSkeleton />
      ) : !summary ? (
        <EmptyState title="No data" message="Could not load the dashboard." />
      ) : (
        <>
          <div className={cn('grid grid-cols-2 gap-4', hasForecast ? 'xl:grid-cols-5' : 'xl:grid-cols-4')}>
            <StatCard
              label="Stock value"
              value={formatCurrency(summary.total_stock_value)}
              icon={<Wallet className="h-4 w-4" />}
              hint={`${formatNumber(summary.active_products)} of ${formatNumber(summary.total_products)} products active`}
            />
            <StatCard
              label={`Units sold (${days}d)`}
              value={formatNumber(totalUnitsOut(series))}
              delta={computeDelta(sparkline)}
              deltaLabel="vs prior half"
              sparkline={sparkline}
            />
            {hasForecast && (
              <StatCard
                label="Projected revenue (30d)"
                value={formatCurrency(forecast.projected_revenue_30d)}
                tone="brand"
                icon={<TrendingUp className="h-4 w-4" />}
                hint={`${formatNumber(forecast.forecasted_count)} products forecasted`}
              />
            )}
            <StatCard
              label="Needs reorder"
              value={formatNumber(summary.reorder_count)}
              tone="warning"
              tinted
              icon={<AlertTriangle className="h-4 w-4" />}
              hint={
                summary.urgent_count > 0
                  ? `${formatNumber(summary.urgent_count)} urgent — order today`
                  : 'from demand forecasts'
              }
              onClick={() => navigate('/recommendations')}
            />
            <StatCard
              label="Out of stock"
              value={formatNumber(summary.out_of_stock_count)}
              tone="danger"
              tinted
              icon={<PackageX className="h-4 w-4" />}
            />
          </div>

          <div className="grid gap-4 lg:grid-cols-3">
            <Card
              className="lg:col-span-2"
              title="Stock movement"
              subtitle="Units sold vs received per day — dashed line is the model projection"
            >
              <div className="p-5">
                {trendsLoading ? (
                  <ChartSkeleton height={260} />
                ) : (
                  <Suspense fallback={<ChartSkeleton height={260} />}>
                    <MovementsTrendChart
                      series={series}
                      projection={hasForecast ? forecast.daily : undefined}
                      height={260}
                    />
                  </Suspense>
                )}
              </div>
            </Card>
            <Card title="Inventory value by category">
              <div className="p-5">
                {trendsLoading ? (
                  <ChartSkeleton height={260} />
                ) : (
                  <Suspense fallback={<ChartSkeleton height={260} />}>
                    <CategoryValueChart data={trends?.category_values ?? []} height={260} />
                  </Suspense>
                )}
              </div>
            </Card>
          </div>

          <div className="grid gap-4 lg:grid-cols-2">
            <Card
              title="Reorder alerts"
              subtitle="From the demand model — most urgent first"
              actions={
                <Link
                  to="/recommendations"
                  className="text-xs font-medium text-brand-600 transition-colors hover:text-brand-700"
                >
                  View all
                </Link>
              }
            >
              {summary.reorder_products.length === 0 ? (
                <EmptyState title="All good!" message="Nothing needs reordering right now." />
              ) : (
                <ul className="divide-y divide-slate-100">
                  {summary.reorder_products.map((rec) => (
                    <li key={rec.product_id} className="px-5 py-3">
                      <div className="flex items-center justify-between gap-3">
                        <div className="flex min-w-0 items-baseline gap-2">
                          <Link
                            to={`/products/${rec.product_id}`}
                            className="truncate text-sm font-medium text-slate-800 transition-colors hover:text-brand-600"
                          >
                            {rec.name}
                          </Link>
                          <span className="shrink-0 font-mono text-xs text-slate-500">{rec.sku}</span>
                        </div>
                        <div className="flex shrink-0 items-center gap-3">
                          <span className="text-sm text-slate-600 tabular-nums">
                            {formatNumber(rec.current_stock)} left · {coverLabel(rec)} · order{' '}
                            {formatNumber(rec.suggested_reorder_qty)}
                          </span>
                          <Badge tone={rec.is_urgent ? 'red' : 'amber'} dot>
                            {rec.is_urgent ? 'Order today' : 'Reorder'}
                          </Badge>
                        </div>
                      </div>
                      {/* Runway vs supplier lead time: an empty bar means it cannot be restocked in time. */}
                      <CapacityBar
                        value={Math.max(0, Math.round(rec.days_of_stock_left ?? 0))}
                        max={rec.lead_time_days}
                        className="mt-2"
                      />
                    </li>
                  ))}
                </ul>
              )}
            </Card>

            <Card title="Recent activity">
              {summary.recent_movements.length === 0 ? (
                <EmptyState title="No activity yet" message="Stock movements will appear here." />
              ) : (
                <ul className="divide-y divide-slate-100">
                  {summary.recent_movements.map((movement) => (
                    <li key={movement.id} className="flex items-center justify-between gap-3 px-5 py-3">
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                          <Badge tone={movementTone(movement.type)} dot>
                            {movement.type_label}
                          </Badge>
                          <Link
                            to={`/products/${movement.product_id}`}
                            className="truncate text-sm font-medium text-slate-800 transition-colors hover:text-brand-600"
                          >
                            {movement.product_name ?? `Product #${movement.product_id}`}
                          </Link>
                        </div>
                        {movement.reason && (
                          <p className="mt-0.5 truncate text-xs text-slate-500">{movement.reason}</p>
                        )}
                      </div>
                      <div className="shrink-0 text-right">
                        <p
                          className={cn(
                            'font-mono text-sm font-medium tabular-nums',
                            movement.change >= 0 ? 'text-success-600' : 'text-danger-600',
                          )}
                        >
                          {movement.change >= 0 ? '+' : ''}
                          {formatNumber(movement.change)}
                        </p>
                        <p className="text-xs text-slate-500">{formatDateTime(movement.created_at)}</p>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </Card>
          </div>
        </>
      )}
    </div>
  )
}
