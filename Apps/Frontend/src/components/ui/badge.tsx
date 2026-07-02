import type { ReactNode } from 'react'
import { cn } from './cn'

/**
 * Tone keys are part of the public contract (lib/recommendation.ts and its
 * tests map verdicts to these literals) — restyle values, never rename keys.
 */
type BadgeTone = 'gray' | 'green' | 'red' | 'amber' | 'indigo'

const badgeTones: Record<BadgeTone, string> = {
  gray: 'bg-slate-50 text-slate-700 ring-slate-600/20',
  green: 'bg-success-50 text-success-700 ring-success-600/25',
  red: 'bg-danger-50 text-danger-700 ring-danger-600/25',
  amber: 'bg-warning-50 text-warning-700 ring-warning-600/30',
  indigo: 'bg-brand-50 text-brand-700 ring-brand-600/25',
}

const dotTones: Record<BadgeTone, string> = {
  gray: 'bg-slate-400',
  green: 'bg-success-600',
  red: 'bg-danger-600',
  amber: 'bg-warning-600',
  indigo: 'bg-brand-600',
}

export function Badge({
  tone = 'gray',
  dot = false,
  children,
}: {
  tone?: BadgeTone
  dot?: boolean
  children: ReactNode
}) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
        badgeTones[tone],
      )}
    >
      {dot && <span className={cn('h-1.5 w-1.5 rounded-full', dotTones[tone])} />}
      {children}
    </span>
  )
}
