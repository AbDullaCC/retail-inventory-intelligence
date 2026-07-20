import type { CSSProperties, ReactNode } from 'react'
import { Loader2 } from 'lucide-react'
import { cn } from './cn'

export function Spinner({ className }: { className?: string }) {
  return <Loader2 className={cn('h-5 w-5 animate-spin text-brand-600', className)} />
}

/** Kept for in-flight route guards; page bodies should prefer skeletons. */
export function PageSpinner({ label = 'Loading…' }: { label?: string }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-slate-500">
      <Spinner className="h-8 w-8" />
      <p className="text-sm">{label}</p>
    </div>
  )
}

export function Skeleton({ className, style }: { className?: string; style?: CSSProperties }) {
  return <div className={cn('animate-pulse rounded-md bg-slate-200/70', className)} style={style} />
}

export function TableSkeleton({ rows = 8, cols = 5 }: { rows?: number; cols?: number }) {
  return (
    <div className="space-y-3 p-4">
      <div className="flex gap-4">
        {Array.from({ length: cols }, (_, i) => (
          <Skeleton key={i} className="h-3 flex-1" />
        ))}
      </div>
      {Array.from({ length: rows }, (_, r) => (
        <div key={r} className="flex gap-4">
          {Array.from({ length: cols }, (_, c) => (
            <Skeleton key={c} className={cn('h-4 flex-1', c === 0 && 'flex-[1.5]')} />
          ))}
        </div>
      ))}
    </div>
  )
}

export function StatCardSkeleton() {
  return (
    <div className="rounded-xl border border-slate-200/70 bg-white p-5 shadow-card">
      <Skeleton className="h-3 w-24" />
      <Skeleton className="mt-3 h-7 w-32" />
      <Skeleton className="mt-2 h-3 w-20" />
    </div>
  )
}

export function ChartSkeleton({ height = 240 }: { height?: number }) {
  return <Skeleton className="w-full" style={{ height }} />
}

export function EmptyState({
  title,
  message,
  action,
  illustration,
}: {
  title: string
  message?: string
  action?: ReactNode
  illustration?: ReactNode
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
      {illustration && (
        <div className="mb-1 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100/80 text-slate-400 ring-1 ring-inset ring-slate-200/60">
          {illustration}
        </div>
      )}
      <p className="text-sm font-medium text-slate-700">{title}</p>
      {message && <p className="max-w-sm text-sm text-slate-400">{message}</p>}
      {action && <div className="mt-2">{action}</div>}
    </div>
  )
}
