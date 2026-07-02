import type { ReactNode, ThHTMLAttributes, TdHTMLAttributes } from 'react'
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight } from 'lucide-react'
import type { PaginationMeta } from '../../types'
import { cn } from './cn'
import { Button } from './button'

export function Table({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div className={cn('overflow-x-auto', className)}>
      <table className="min-w-full divide-y divide-slate-200 text-sm">{children}</table>
    </div>
  )
}

export function THead({ children }: { children: ReactNode }) {
  return (
    <thead className="bg-slate-50/60">
      <tr>{children}</tr>
    </thead>
  )
}

export function TBody({ children }: { children: ReactNode }) {
  return <tbody className="divide-y divide-slate-100 bg-white">{children}</tbody>
}

export type SortDir = 'asc' | 'desc'

interface THProps extends ThHTMLAttributes<HTMLTableCellElement> {
  align?: 'left' | 'right'
  /** Renders a sort affordance; active direction shown when sortDir is set. */
  sortable?: boolean
  sortDir?: SortDir | null
  onSort?: () => void
}

export function TH({ align = 'left', sortable, sortDir, onSort, className, children, ...rest }: THProps) {
  const label = sortable ? (
    <button
      type="button"
      onClick={onSort}
      className={cn(
        'inline-flex items-center gap-1 transition-colors hover:text-slate-800',
        sortDir && 'text-slate-800',
      )}
    >
      {children}
      {sortDir === 'asc' && <ArrowUp className="h-3 w-3" />}
      {sortDir === 'desc' && <ArrowDown className="h-3 w-3" />}
      {!sortDir && <ArrowUpDown className="h-3 w-3 opacity-50" />}
    </button>
  ) : (
    children
  )

  return (
    <th
      className={cn(
        'px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500',
        align === 'right' ? 'text-right' : 'text-left',
        className,
      )}
      {...rest}
    >
      {label}
    </th>
  )
}

interface TDProps extends TdHTMLAttributes<HTMLTableCellElement> {
  /** Right-aligned tabular figures — use for every numeric cell. */
  numeric?: boolean
}

export function TD({ numeric, className, children, ...rest }: TDProps) {
  return (
    <td className={cn('px-4 py-3', numeric && 'text-right tabular-nums', className)} {...rest}>
      {children}
    </td>
  )
}

export function Pagination({ meta, onPage }: { meta: PaginationMeta; onPage: (page: number) => void }) {
  return (
    <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
      <span className="tabular-nums">
        {meta.from ?? 0}–{meta.to ?? 0} of {meta.total}
      </span>
      <div className="flex items-center gap-2">
        <Button
          variant="secondary"
          size="sm"
          disabled={meta.current_page <= 1}
          onClick={() => onPage(meta.current_page - 1)}
          aria-label="Previous page"
        >
          <ChevronLeft className="h-4 w-4" />
          Previous
        </Button>
        <span className="px-1 tabular-nums">
          {meta.current_page} / {meta.last_page}
        </span>
        <Button
          variant="secondary"
          size="sm"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}
          aria-label="Next page"
        >
          Next
          <ChevronRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  )
}
