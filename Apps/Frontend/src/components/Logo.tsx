import { APP_NAME } from '../lib/brand'
import { cn } from './ui'

/**
 * Brand mark: shelf bars with a rising demand line — inventory + intelligence
 * in one glyph. Inline SVG so it needs no asset pipeline and inherits sizing.
 */
export function LogoMark({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      className={cn('h-8 w-8', className)}
      aria-hidden="true"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect width="24" height="24" rx="6" fill="var(--color-brand-600)" />
      <rect x="4" y="13" width="16" height="2" rx="1" fill="white" opacity="0.55" />
      <rect x="4" y="17" width="16" height="2" rx="1" fill="white" opacity="0.55" />
      <polyline
        points="4,11 10,7 14,9 20,4"
        fill="none"
        stroke="white"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <circle cx="20" cy="4" r="1.6" fill="white" />
    </svg>
  )
}

export function LogoWordmark({ light = false, className }: { light?: boolean; className?: string }) {
  return (
    <span className={cn('inline-flex items-center gap-2.5', className)}>
      <LogoMark />
      <span
        className={cn(
          'text-lg font-semibold tracking-tight',
          light ? 'text-white' : 'text-slate-900',
        )}
      >
        {APP_NAME}
      </span>
    </span>
  )
}
