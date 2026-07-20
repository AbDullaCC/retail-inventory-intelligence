import type { ReactNode } from 'react'
import { cn } from './cn'

interface CardProps {
  className?: string
  children: ReactNode
  /** Renders a header bar: title/subtitle left, actions right. */
  title?: string
  subtitle?: ReactNode
  actions?: ReactNode
}

export function Card({ className, children, title, subtitle, actions }: CardProps) {
  return (
    <div className={cn('rounded-xl border border-slate-200/70 bg-white shadow-card', className)}>
      {(title || actions) && (
        <div className="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5">
          <div>
            {title && <h2 className="text-sm font-semibold text-slate-900">{title}</h2>}
            {subtitle && <p className="mt-0.5 text-xs text-slate-500">{subtitle}</p>}
          </div>
          {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
        </div>
      )}
      {children}
    </div>
  )
}
