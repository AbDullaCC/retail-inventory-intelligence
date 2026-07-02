import { cn } from './cn'

export interface SegmentedOption<T extends string> {
  value: T
  label: string
  count?: number
}

interface SegmentedControlProps<T extends string> {
  options: Array<SegmentedOption<T>>
  value: T
  onChange: (value: T) => void
  className?: string
}

export function SegmentedControl<T extends string>({
  options,
  value,
  onChange,
  className,
}: SegmentedControlProps<T>) {
  return (
    <div
      role="tablist"
      className={cn('inline-flex flex-wrap items-center gap-1 rounded-lg bg-slate-100 p-1', className)}
    >
      {options.map((option) => {
        const active = option.value === value
        return (
          <button
            key={option.value}
            type="button"
            role="tab"
            aria-selected={active}
            onClick={() => onChange(option.value)}
            className={cn(
              'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition-colors',
              active
                ? 'bg-white font-medium text-slate-900 shadow-card'
                : 'text-slate-500 hover:text-slate-800',
            )}
          >
            {option.label}
            {option.count !== undefined && (
              <span
                className={cn(
                  'rounded-full px-1.5 text-xs tabular-nums',
                  active ? 'bg-slate-100 text-slate-600' : 'bg-slate-200/70 text-slate-500',
                )}
              >
                {option.count}
              </span>
            )}
          </button>
        )
      })}
    </div>
  )
}
