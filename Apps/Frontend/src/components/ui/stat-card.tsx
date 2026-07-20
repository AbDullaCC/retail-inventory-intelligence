import type { ReactNode } from 'react'
import { TrendingDown, TrendingUp } from 'lucide-react'
import { formatDelta } from '../../lib/format'
import { cn } from './cn'

/** Dependency-free sparkline (no chart library on the KPI path). */
export function Sparkline({ values, className }: { values: number[]; className?: string }) {
  if (values.length < 2) return null

  const w = 96
  const h = 28
  const pad = 2
  const min = Math.min(...values)
  const max = Math.max(...values)
  const span = max - min || 1
  const points = values
    .map((v, i) => {
      const x = pad + (i / (values.length - 1)) * (w - pad * 2)
      const y = h - pad - ((v - min) / span) * (h - pad * 2)
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')

  return (
    <svg
      viewBox={`0 0 ${w} ${h}`}
      className={cn('h-7 w-24 text-brand-500', className)}
      aria-hidden="true"
    >
      <polyline
        points={points}
        fill="none"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

const statTones = {
  default: 'text-slate-500',
  brand: 'text-brand-600',
  success: 'text-success-600',
  warning: 'text-warning-600',
  danger: 'text-danger-600',
}

const activeRings = {
  default: 'border-slate-400 ring-1 ring-slate-400',
  brand: 'border-brand-500 ring-1 ring-brand-500',
  success: 'border-success-500 ring-1 ring-success-500',
  warning: 'border-warning-500 ring-1 ring-warning-500',
  danger: 'border-danger-500 ring-1 ring-danger-500',
}

/** Soft tone wash for `tinted` cards — deliberately quiet next to bg-white. */
const tintedBg = {
  default: 'bg-slate-50/60',
  brand: 'bg-brand-50/60',
  success: 'bg-success-50/60',
  warning: 'bg-warning-50/60',
  danger: 'bg-danger-50/60',
}

const tintedBorder = {
  default: 'border-slate-200/70',
  brand: 'border-brand-100',
  success: 'border-success-100',
  warning: 'border-warning-100',
  danger: 'border-danger-100',
}

const iconChips = {
  default: 'bg-slate-100 text-slate-500',
  brand: 'bg-brand-100 text-brand-600',
  success: 'bg-success-100 text-success-600',
  warning: 'bg-warning-100 text-warning-600',
  danger: 'bg-danger-100 text-danger-600',
}

interface StatCardProps {
  label: string
  value: string
  icon?: ReactNode
  tone?: keyof typeof statTones
  /** Percent change chip; positive renders green/up, negative red/down. */
  delta?: number | null
  deltaLabel?: string
  sparkline?: number[]
  hint?: string
  /** Renders the card as a button (e.g. a KPI that doubles as a filter). */
  onClick?: () => void
  /** Highlights the card with a tone-coloured ring while its filter is applied. */
  active?: boolean
  /** Soft tone-washed surface with the icon in a chip (verdict KPIs). */
  tinted?: boolean
}

export function StatCard({ label, value, icon, tone = 'default', delta, deltaLabel, sparkline, hint, onClick, active, tinted }: StatCardProps) {
  const showDelta = delta !== undefined && delta !== null && Number.isFinite(delta)
  const Wrapper = onClick ? 'button' : 'div'

  return (
    <Wrapper
      {...(onClick ? { type: 'button' as const, onClick, 'aria-pressed': active } : {})}
      className={cn(
        'rounded-xl border p-5 text-left shadow-card',
        tinted ? tintedBg[tone] : 'bg-white',
        // Exactly one border class — cn() does not resolve Tailwind conflicts.
        active ? activeRings[tone] : tinted ? tintedBorder[tone] : 'border-slate-200/70',
        onClick && 'cursor-pointer transition-shadow hover:shadow-pop',
      )}
    >
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</p>
        {icon && (
          <span className={cn('shrink-0', tinted ? cn('rounded-lg p-2', iconChips[tone]) : statTones[tone])}>
            {icon}
          </span>
        )}
      </div>
      <div className="mt-2 flex items-end justify-between gap-3">
        <div>
          <p className="text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">{value}</p>
          {(showDelta || hint) && (
            <p className="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
              {showDelta && (
                <span
                  className={cn(
                    'inline-flex items-center gap-0.5 font-medium tabular-nums',
                    delta >= 0 ? 'text-success-700' : 'text-danger-700',
                  )}
                >
                  {delta >= 0 ? <TrendingUp className="h-3 w-3" /> : <TrendingDown className="h-3 w-3" />}
                  {formatDelta(delta)}
                </span>
              )}
              {deltaLabel && showDelta && <span>{deltaLabel}</span>}
              {hint && <span>{hint}</span>}
            </p>
          )}
        </div>
        {sparkline && sparkline.length > 1 && <Sparkline values={sparkline} />}
      </div>
    </Wrapper>
  )
}
