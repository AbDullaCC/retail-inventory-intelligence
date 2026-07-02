import { cn } from './cn'

/**
 * Stock-vs-reorder-level bar: full width represents 2× the reorder level, so
 * a healthy product sits comfortably past the middle. Tone is automatic:
 * empty → danger, at/below the reorder level → warning, above → brand.
 */
export function CapacityBar({
  value,
  max,
  className,
}: {
  value: number
  max: number
  className?: string
}) {
  const pct = max > 0 ? Math.min(100, (value / (max * 2)) * 100) : value > 0 ? 100 : 0
  const tone = value === 0 ? 'bg-danger-600' : value <= max ? 'bg-warning-600' : 'bg-brand-500'

  return (
    <div className={cn('h-1.5 w-full overflow-hidden rounded-full bg-slate-100', className)}>
      <div className={cn('h-full rounded-full transition-all', tone)} style={{ width: `${pct}%` }} />
    </div>
  )
}
