const currencyFormatter = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
})

const numberFormatter = new Intl.NumberFormat('en-US')

export function formatCurrency(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—'
  return currencyFormatter.format(value)
}

export function formatNumber(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—'
  return numberFormatter.format(value)
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const compactCurrencyFormatter = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
  notation: 'compact',
  minimumFractionDigits: 0,
  maximumFractionDigits: 1,
})

/** "$12.4K" — for KPI cards where space is tight. */
export function formatCompactCurrency(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—'
  return compactCurrencyFormatter.format(value)
}

/** "+12.5%" / "−3.2%" — signed percentage for trend deltas. */
export function formatDelta(value: number | null | undefined): string {
  if (value === null || value === undefined || !Number.isFinite(value)) return '—'
  const rounded = Math.round(value * 10) / 10
  return `${rounded > 0 ? '+' : ''}${rounded}%`
}

/** "Jun 3" — short date for chart ticks and compact tables. */
export function formatShortDate(value: string | null | undefined): string {
  if (!value) return '—'
  const date = new Date(`${value}T00:00:00`)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}
