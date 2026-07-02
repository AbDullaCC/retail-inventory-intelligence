import { cn } from './cn'

export function Avatar({ name, className }: { name: string; className?: string }) {
  const initials = name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]!.toUpperCase())
    .join('')

  return (
    <span
      className={cn(
        'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700',
        className,
      )}
      aria-hidden="true"
    >
      {initials || '?'}
    </span>
  )
}
