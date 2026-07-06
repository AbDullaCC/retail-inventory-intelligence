import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import toast from 'react-hot-toast'
import { ExternalLink, Plug, RefreshCw, Store, TrendingUp } from 'lucide-react'
import { shopifyApi } from '../api/shopify'
import { forecastApi } from '../api/forecast'
import { apiErrorMessage } from '../lib/api'
import { formatDateTime, formatNumber } from '../lib/format'
import { usePageTitle } from '../lib/usePageTitle'
import {
  Badge,
  Button,
  Card,
  ConfirmDialog,
  Field,
  Input,
  PageHeader,
  Skeleton,
} from '../components/ui'
import type { ShopifyStatus } from '../types'

const setupSteps = [
  'In your Shopify admin, open Settings → Apps and sales channels → Develop apps and create an app.',
  'Grant the Admin API scopes read_products, read_inventory and read_orders (add read_all_orders for history older than 60 days).',
  'Install the app and copy the Admin API access token (starts with "shpat_").',
  'Paste your store domain and the token here and connect.',
]

export function IntegrationsPage() {
  usePageTitle('Integrations')

  const [status, setStatus] = useState<ShopifyStatus | null>(null)
  const [loading, setLoading] = useState(true)
  const [domain, setDomain] = useState('')
  const [token, setToken] = useState('')
  const [connecting, setConnecting] = useState(false)
  const [syncing, setSyncing] = useState(false)
  const [forecasting, setForecasting] = useState(false)
  const [confirmDisconnect, setConfirmDisconnect] = useState(false)
  const [disconnecting, setDisconnecting] = useState(false)

  const refresh = useCallback(async () => {
    try {
      setStatus(await shopifyApi.status())
    } catch (error) {
      toast.error(apiErrorMessage(error, 'Could not load integration status.'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void refresh()
  }, [refresh])

  const onConnect = async (e: FormEvent) => {
    e.preventDefault()
    setConnecting(true)
    try {
      const next = await shopifyApi.connect(domain, token)
      setStatus(next)
      setToken('')
      toast.success(`Connected to ${next.shop_name ?? next.domain}.`)
    } catch (error) {
      toast.error(apiErrorMessage(error, 'Could not connect to Shopify.'))
    } finally {
      setConnecting(false)
    }
  }

  const onSync = async () => {
    setSyncing(true)
    try {
      const result = await shopifyApi.sync()
      setStatus(result.status)
      toast.success(
        result.stats.backfill
          ? `Imported ${formatNumber(result.stats.order_lines_imported)} historical sales — refresh forecasts to model them.`
          : 'Store synced.',
      )
    } catch (error) {
      toast.error(apiErrorMessage(error, 'Sync failed.'))
    } finally {
      setSyncing(false)
    }
  }

  const onRunForecasts = async () => {
    setForecasting(true)
    try {
      const summary = await forecastApi.run()
      toast.success(`Forecasted ${formatNumber(summary.forecasted)} products.`)
    } catch (error) {
      toast.error(apiErrorMessage(error, 'Forecast refresh failed — is the forecast service running?'))
    } finally {
      setForecasting(false)
    }
  }

  const onDisconnect = async () => {
    setDisconnecting(true)
    try {
      await shopifyApi.disconnect()
      setConfirmDisconnect(false)
      toast.success('Shopify disconnected. Imported products and history are kept.')
      await refresh()
    } catch (error) {
      toast.error(apiErrorMessage(error, 'Could not disconnect.'))
    } finally {
      setDisconnecting(false)
    }
  }

  return (
    <>
      <PageHeader
        title="Integrations"
        description="Connect the tools your store already uses — the intelligence runs on your own data."
      />

      <Card
        title="Shopify"
        subtitle="Products, order history and stock levels — read-only, your store is never modified."
        actions={
          status?.connected ? (
            <Badge tone="green" dot>
              Connected
            </Badge>
          ) : (
            <Badge tone="gray">Not connected</Badge>
          )
        }
      >
        {loading ? (
          <div className="space-y-3 p-5">
            <Skeleton className="h-4 w-64" />
            <Skeleton className="h-4 w-80" />
            <Skeleton className="h-9 w-40" />
          </div>
        ) : status?.connected ? (
          <ConnectedPanel
            status={status}
            syncing={syncing}
            forecasting={forecasting}
            onSync={() => void onSync()}
            onRunForecasts={() => void onRunForecasts()}
            onDisconnect={() => setConfirmDisconnect(true)}
          />
        ) : (
          <div className="grid gap-8 p-5 lg:grid-cols-2">
            <div>
              <p className="flex items-center gap-2 text-sm font-medium text-slate-900">
                <Plug className="h-4 w-4 text-brand-600" />
                How to connect
              </p>
              <ol className="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-600">
                {setupSteps.map((step) => (
                  <li key={step}>{step}</li>
                ))}
              </ol>
              <a
                href="https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens/generate-app-access-tokens-admin"
                target="_blank"
                rel="noreferrer"
                className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:underline"
              >
                Shopify's guide to custom app tokens
                <ExternalLink className="h-3.5 w-3.5" />
              </a>
            </div>

            <form onSubmit={onConnect} className="space-y-4">
              <Field label="Store domain" htmlFor="shopify-domain" required>
                <Input
                  id="shopify-domain"
                  value={domain}
                  onChange={(e) => setDomain(e.target.value)}
                  placeholder="your-store.myshopify.com"
                  required
                />
              </Field>
              <Field
                label="Admin API access token"
                htmlFor="shopify-token"
                hint="Stored encrypted; used only to read your store."
                required
              >
                <Input
                  id="shopify-token"
                  type="password"
                  value={token}
                  onChange={(e) => setToken(e.target.value)}
                  placeholder="shpat_…"
                  autoComplete="off"
                  required
                />
              </Field>
              <Button type="submit" loading={connecting}>
                <Store className="h-4 w-4" />
                Connect store
              </Button>
            </form>
          </div>
        )}
      </Card>

      <ConfirmDialog
        open={confirmDisconnect}
        onClose={() => setConfirmDisconnect(false)}
        onConfirm={() => void onDisconnect()}
        title="Disconnect Shopify"
        message="The stored credentials will be removed. Products and sales history already imported are kept."
        confirmLabel="Disconnect"
        loading={disconnecting}
      />
    </>
  )
}

function ConnectedPanel({
  status,
  syncing,
  forecasting,
  onSync,
  onRunForecasts,
  onDisconnect,
}: {
  status: ShopifyStatus
  syncing: boolean
  forecasting: boolean
  onSync: () => void
  onRunForecasts: () => void
  onDisconnect: () => void
}) {
  const stats = status.last_stats

  return (
    <div className="p-5">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-sm font-semibold text-slate-900">{status.shop_name ?? status.domain}</p>
          <p className="mt-0.5 font-mono text-xs text-slate-400">{status.domain}</p>
          <p className="mt-2 text-xs text-slate-500">
            {status.source === 'env'
              ? 'Configured via environment variables.'
              : status.last_synced_at
                ? `Last synced ${formatDateTime(status.last_synced_at)}`
                : 'Not synced yet — run the first sync to import the catalogue and order history.'}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button onClick={onSync} loading={syncing}>
            <RefreshCw className="h-4 w-4" />
            {status.last_synced_at ? 'Sync now' : 'Import store'}
          </Button>
          <Button variant="secondary" onClick={onRunForecasts} loading={forecasting}>
            <TrendingUp className="h-4 w-4" />
            Refresh forecasts
          </Button>
          {status.source === 'ui' && (
            <Button variant="ghost" onClick={onDisconnect}>
              Disconnect
            </Button>
          )}
        </div>
      </div>

      {syncing && (
        <p className="mt-3 text-xs text-slate-400">
          Importing the catalogue and order history — a first run can take a minute…
        </p>
      )}

      {stats && (
        <dl className="mt-5 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-3 lg:grid-cols-6">
          <Stat label="Products created" value={stats.products_created} />
          <Stat label="Products updated" value={stats.products_updated} />
          <Stat label="Orders imported" value={stats.orders_imported} />
          <Stat label="Sales lines" value={stats.order_lines_imported} />
          <Stat label="Stock adjustments" value={stats.inventory_adjustments} />
          <Stat label="Conflicts" value={stats.order_lines_conflicted} />
        </dl>
      )}
    </div>
  )
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div>
      <dt className="text-[11px] font-medium uppercase tracking-wide text-slate-400">{label}</dt>
      <dd className="mt-0.5 text-sm font-semibold text-slate-900 tabular-nums">{formatNumber(value)}</dd>
    </div>
  )
}
