import type { ButtonHTMLAttributes } from 'react'
import { Loader2 } from 'lucide-react'
import { cn } from './cn'

type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant
  size?: 'xs' | 'sm' | 'md'
  loading?: boolean
}

const buttonVariants: Record<ButtonVariant, string> = {
  primary: 'bg-brand-600 text-white shadow-card hover:bg-brand-700 disabled:bg-brand-300',
  secondary:
    'bg-white text-slate-700 border border-slate-300 shadow-card hover:border-slate-400 hover:bg-slate-50 disabled:opacity-60',
  danger: 'bg-danger-600 text-white shadow-card hover:bg-danger-700 disabled:bg-red-300',
  ghost: 'bg-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900 disabled:opacity-60',
}

const buttonSizes = {
  xs: 'px-2 py-1 text-xs',
  sm: 'px-3 py-1.5 text-sm',
  md: 'px-4 py-2 text-sm',
}

export function Button({
  variant = 'primary',
  size = 'md',
  loading = false,
  className,
  children,
  disabled,
  ...rest
}: ButtonProps) {
  return (
    <button
      className={cn(
        'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 active:translate-y-px disabled:cursor-not-allowed disabled:active:translate-y-0',
        buttonSizes[size],
        buttonVariants[variant],
        className,
      )}
      disabled={disabled || loading}
      {...rest}
    >
      {loading && <Loader2 className="h-4 w-4 animate-spin" />}
      {children}
    </button>
  )
}
