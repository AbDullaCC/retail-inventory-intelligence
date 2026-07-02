import type { ReactNode } from 'react'
import { cn } from './cn'

/**
 * Lightweight hover/focus tooltip — CSS-only, no portal. Callers must ensure
 * the trigger is not inside an overflow-hidden container.
 */
export function Tooltip({
  content,
  children,
  className,
}: {
  content: ReactNode
  children: ReactNode
  className?: string
}) {
  return (
    <span className={cn('group relative inline-flex', className)}>
      {children}
      <span
        role="tooltip"
        className="pointer-events-none absolute bottom-full left-1/2 z-50 mb-1.5 hidden max-w-64 -translate-x-1/2 whitespace-normal rounded-md bg-slate-900 px-2 py-1 text-center text-xs text-white shadow-pop group-hover:block group-focus-within:block"
      >
        {content}
      </span>
    </span>
  )
}
