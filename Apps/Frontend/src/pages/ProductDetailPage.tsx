import { lazy, Suspense, useCallback, useEffect, useState } from 'react'
import type { FormEvent, ReactNode } from 'react'
import { Link, useParams } from 'react-router-dom'
import toast from 'react-hot-toast'
import { TrendingDown, TrendingUp } from 'lucide-react'
import { productsApi } from '../api/products'
import { stockApi } from '../api/stock'
import { intelligenceApi } from '../api/intelligence'
import { forecastApi } from '../api/forecast'
import { apiErrorMessage } from '../lib/api'
import { perWeek, recommendationPresentation, reorderByLabel } from '../lib/recommendation'
import {
  formatCurrency,
  formatDateTime,
  formatDelta,
  formatNumber,
  formatShortDate,
} from '../lib/format'
import { usePageTitle } from '../lib/usePageTitle'
import {
  Badge,
  Button,
  Card,
  ChartSkeleton,
  cn,
  EmptyState,
  Field,
  Input,
  PageHeader,
  Pagination,
  SegmentedControl,
  Skeleton,
  Table,
  TableSkeleton,
  TBody,
  TD,
  TH,
  THead,
} from '../components/ui'
import type { SegmentedOption } from '../components/ui'
import { StockStatusBadge } from '../components/StockStatusBadge'
import type {
  ApiPaginated,
  Product,
  ProductForecast,
  Recommendation,
  RecommendationType,
  StockMovement,
  StockMovementType,
  StockoutRisk,
} from '../types'

const HistoryForecastChart = lazy(() => import('../components/charts/HistoryForecastChart'))

/** Left-edge hero tint per verdict — matches recommendationPresentation tones. */
const heroBorders: Record<RecommendationType, string> = {
  reorder: 'border-l-danger-600',
  overstock: 'border-l-warning-600',
  dead_stock: 'border-l-slate-400',
  healthy: 'border-l-success-600',
}

const riskTones: Record<StockoutRisk, 'red' | 'amber' | 'green'> = {
  high: 'red',
  medium: 'amber',
  low: 'green',
}

function TrendChip({ pct }: { pct: number | null }) {
  if (pct === null) return null
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

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4 py-2">
      <span className="text-sm text-slate-500">{label}</span>
      <span className="text-right text-sm font-medium tabular-nums text-slate-800">{value}</span>
    </div>
  )
}

function Metric({ label, value, urgent }: { label: string; value: string; urgent?: boolean }) {
  return (
    <div>
      <p className="text-xs uppercase tracking-wide text-slate-400">{label}</p>
      <p
        className={cn(
          'text-sm font-semibold tabular-nums',
          urgent ? 'text-danger-600' : 'text-slate-800',
        )}
      >
        {value}
      </p>
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

const movementOptions: Array<SegmentedOption<StockMovementType>> = [
  { value: 'in', label: 'Stock in' },
  { value: 'out', label: 'Stock out' },
  { value: 'adjustment', label: 'Set exact' },
]

export function ProductDetailPage() {
  const { id } = useParams<{ id: string }>()
  const productId = Number(id)

  const [product, setProduct] = useState<Product | null>(null)
  const [history, setHistory] = useState<ApiPaginated<StockMovement> | null>(null)
  const [recommendation, setRecommendation] = useState<Recommendation | null>(null)
  const [loading, setLoading] = useState(true)

  const [forecast, setForecast] = useState<ProductForecast | null>(null)
  const [forecastLoading, setForecastLoading] = useState(true)

  const [type, setType] = useState<StockMovementType>('in')
  const [quantity, setQuantity] = useState('1')
  const [reason, setReason] = useState('')
  const [adjusting, setAdjusting] = useState(false)

  usePageTitle(product?.name ?? 'Product')

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

  const loadRecommendation = useCallback(() => {
    return intelligenceApi.forProduct(productId).then(setRecommendation)
  }, [productId])

  useEffect(() => {
    if (Number.isNaN(productId)) {
      setLoading(false)
      return
    }
    Promise.all([loadProduct(), loadHistory(1)])
      .catch((error) => toast.error(apiErrorMessage(error)))
      .finally(() => setLoading(false))
    // Recommendation is supplementary — don't block or blank the page if it fails.
    loadRecommendation().catch(() => undefined)
  }, [productId, loadProduct, loadHistory, loadRecommendation])

  // Forecast is non-blocking with its own loading state; failures degrade quietly.
  useEffect(() => {
    if (Number.isNaN(productId)) {
      setForecastLoading(false)
      return
    }
    setForecastLoading(true)
    forecastApi
      .forProduct(productId)
      .then(setForecast)
      .catch(() => setForecast(null))
      .finally(() => setForecastLoading(false))
  }, [productId])

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
      await Promise.all([loadProduct(), loadHistory(1), loadRecommendation()])
    } catch (error) {
      toast.error(apiErrorMessage(error))
    } finally {
      setAdjusting(false)
    }
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="space-y-2">
          <Skeleton className="h-3 w-32" />
          <Skeleton className="h-8 w-72" />
          <Skeleton className="h-4 w-48" />
        </div>
        <Skeleton className="h-36 w-full rounded-xl" />
        <div className="grid gap-4 lg:grid-cols-3">
          <div className="space-y-4 lg:col-span-2">
            <Card>
              <div className="p-4">
                <ChartSkeleton height={240} />
              </div>
            </Card>
            <Card>
              <TableSkeleton rows={6} cols={6} />
            </Card>
          </div>
          <div className="space-y-4">
            <Card>
              <div className="space-y-3 p-5">
                {Array.from({ length: 6 }, (_, i) => (
                  <Skeleton key={i} className="h-4 w-full" />
                ))}
              </div>
            </Card>
            <Card>
              <div className="space-y-3 p-5">
                <Skeleton className="h-8 w-full" />
                <Skeleton className="h-9 w-full" />
                <Skeleton className="h-9 w-full" />
              </div>
            </Card>
          </div>
        </div>
      </div>
    )
  }

  if (!product) {
    return (
      <EmptyState
        title="Product not found"
        message="It may have been removed."
        action={
          <Link to="/products" className="text-sm font-medium text-brand-600 hover:underline">
            Back to products
          </Link>
        }
      />
    )
  }

  const forecastSubtitle = forecastLoading
    ? undefined
    : forecast && forecast.forecast.length > 0
      ? `${forecast.model_used ?? 'Model'} model · dashed = next ${
          forecast.horizon_days ?? forecast.forecast.length
        } days`
      : 'No fresh forecast — run php artisan forecast:run'

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: 'Products', to: '/products' }]}
        title={product.name}
        description={
          <span className="flex flex-wrap items-center gap-2">
            <span className="font-mono text-xs text-slate-500">{product.sku}</span>
            <Badge tone="indigo">{product.category?.name ?? 'Uncategorised'}</Badge>
            <StockStatusBadge product={product} />
          </span>
        }
      />

      <div className="space-y-4">
        {recommendation && (
          <Card className={cn('border-l-4', heroBorders[recommendation.type])}>
            <div className="p-5">
              <div className="flex flex-wrap items-center gap-2">
                <Badge tone={recommendationPresentation(recommendation.type).tone}>
                  {recommendationPresentation(recommendation.type).label}
                </Badge>
                {recommendation.stockout_risk && (
                  <Badge tone={riskTones[recommendation.stockout_risk]}>
                    {recommendation.stockout_risk} risk
                  </Badge>
                )}
                <TrendChip pct={recommendation.demand_trend_pct} />
                <span className="ml-auto text-xs text-slate-400">
                  {recommendation.forecast_source === 'model'
                    ? `Forecast (${recommendation.model_used ?? 'model'}) · ${formatDateTime(
                        recommendation.forecast_generated_at,
                      )}`
                    : '14-day average (no fresh forecast)'}
                </span>
              </div>

              <p className="mt-3 text-sm text-slate-700">{recommendation.reasoning}</p>

              {recommendation.needs_reorder && (
                <p
                  className={cn(
                    'mt-2 text-sm font-semibold',
                    recommendation.is_urgent ? 'text-danger-700' : 'text-slate-900',
                  )}
                >
                  Order {formatNumber(recommendation.suggested_reorder_qty)} units by{' '}
                  {reorderByLabel(recommendation)}
                </p>
              )}

              <div
                className={cn(
                  'mt-4 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4',
                  recommendation.projected_stockout_date ? 'md:grid-cols-5' : 'md:grid-cols-4',
                )}
              >
                <Metric
                  label="Sales/week"
                  value={formatNumber(perWeek(recommendation.sales_velocity))}
                />
                <Metric
                  label="Days of cover"
                  value={
                    recommendation.days_of_stock_left === null
                      ? '—'
                      : `${Math.round(recommendation.days_of_stock_left)} days`
                  }
                />
                <Metric
                  label="Suggested order"
                  value={
                    recommendation.needs_reorder
                      ? formatNumber(recommendation.suggested_reorder_qty)
                      : '—'
                  }
                />
                <Metric
                  label="Order by"
                  value={recommendation.needs_reorder ? reorderByLabel(recommendation) : '—'}
                  urgent={recommendation.is_urgent}
                />
                {recommendation.projected_stockout_date && (
                  <Metric
                    label="Projected stockout"
                    value={formatShortDate(recommendation.projected_stockout_date)}
                    urgent={recommendation.stockout_risk === 'high'}
                  />
                )}
              </div>
            </div>
          </Card>
        )}

        <div className="grid gap-4 lg:grid-cols-3">
          <div className="space-y-4 lg:col-span-2">
            <Card title="Demand & forecast" subtitle={forecastSubtitle}>
              <div className="p-4">
                {forecastLoading ? (
                  <ChartSkeleton height={240} />
                ) : forecast && forecast.history.length > 0 ? (
                  <Suspense fallback={<ChartSkeleton height={240} />}>
                    <HistoryForecastChart
                      history={forecast.history}
                      forecast={forecast.forecast}
                      valueLabel="units sold"
                    />
                  </Suspense>
                ) : (
                  <EmptyState
                    title="Forecast unavailable"
                    message="Demand history will appear here once sales are recorded and the forecast job has run."
                  />
                )}
              </div>
            </Card>

            <Card title="Movement history">
              {!history || history.data.length === 0 ? (
                <EmptyState title="No movements yet" message="Stock changes will be logged here." />
              ) : (
                <>
                  <Table>
                    <THead>
                      <TH>When</TH>
                      <TH>Type</TH>
                      <TH align="right">Change</TH>
                      <TH align="right">Level</TH>
                      <TH>Reason</TH>
                      <TH>By</TH>
                    </THead>
                    <TBody>
                      {history.data.map((m) => (
                        <tr key={m.id} className="transition-colors hover:bg-slate-50">
                          <TD className="whitespace-nowrap text-slate-500">
                            {formatDateTime(m.created_at)}
                          </TD>
                          <TD>
                            <Badge dot tone={movementTone(m.type)}>
                              {m.type_label}
                            </Badge>
                          </TD>
                          <TD
                            numeric
                            className={cn(
                              'font-mono font-medium',
                              m.change >= 0 ? 'text-success-700' : 'text-danger-700',
                            )}
                          >
                            {m.change >= 0 ? '+' : ''}
                            {m.change}
                          </TD>
                          <TD numeric className="whitespace-nowrap font-mono text-xs text-slate-500">
                            {m.quantity_before} → {m.quantity_after}
                          </TD>
                          <TD className="max-w-40 truncate text-slate-500" title={m.reason ?? undefined}>
                            {m.reason ?? '—'}
                          </TD>
                          <TD className="text-slate-500">{m.user_name ?? '—'}</TD>
                        </tr>
                      ))}
                    </TBody>
                  </Table>
                  <Pagination meta={history.meta} onPage={(page) => void loadHistory(page)} />
                </>
              )}
            </Card>
          </div>

          <div className="space-y-4">
            <Card title="Details">
              <div className="p-5">
                {product.description && (
                  <p className="mb-3 text-sm text-slate-500">{product.description}</p>
                )}
                <div className="divide-y divide-slate-100">
                  <DetailRow label="On hand" value={`${formatNumber(product.quantity)} units`} />
                  <DetailRow label="Reorder level" value={formatNumber(product.reorder_level)} />
                  <DetailRow label="Price" value={formatCurrency(product.price)} />
                  <DetailRow label="Cost" value={formatCurrency(product.cost)} />
                  <DetailRow label="Stock value" value={formatCurrency(product.stock_value)} />
                  <DetailRow
                    label="Status"
                    value={
                      product.is_active ? (
                        <Badge tone="green">Active</Badge>
                      ) : (
                        <Badge tone="gray">Inactive</Badge>
                      )
                    }
                  />
                </div>
              </div>
            </Card>

            <Card title="Adjust stock">
              <form onSubmit={onAdjust} className="space-y-3 p-5">
                <div>
                  <span className="mb-1 block text-sm font-medium text-slate-700">
                    Movement type
                  </span>
                  <SegmentedControl options={movementOptions} value={type} onChange={setType} />
                </div>
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
        </div>
      </div>
    </div>
  )
}
