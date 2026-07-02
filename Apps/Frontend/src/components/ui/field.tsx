import type {
  InputHTMLAttributes,
  ReactNode,
  SelectHTMLAttributes,
  TextareaHTMLAttributes,
} from 'react'
import { ChevronDown } from 'lucide-react'
import { cn } from './cn'

const control =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-card placeholder:text-slate-400 transition-colors focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/25 disabled:cursor-not-allowed disabled:bg-slate-50'

export function Input({ className, ...rest }: InputHTMLAttributes<HTMLInputElement>) {
  return <input className={cn(control, className)} {...rest} />
}

export function Textarea({ className, ...rest }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea className={cn(control, className)} {...rest} />
}

export function Select({ className, children, ...rest }: SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <div className={cn('relative', className)}>
      <select className={cn(control, 'appearance-none pr-8')} {...rest}>
        {children}
      </select>
      <ChevronDown className="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
    </div>
  )
}

interface CheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label: string
}

export function Checkbox({ label, className, ...rest }: CheckboxProps) {
  return (
    <label
      className={cn(
        'inline-flex cursor-pointer select-none items-center gap-2 text-sm text-slate-700',
        className,
      )}
    >
      <input type="checkbox" className="h-4 w-4 rounded accent-brand-600" {...rest} />
      {label}
    </label>
  )
}

interface FieldProps {
  label: string
  htmlFor?: string
  error?: string
  hint?: string
  required?: boolean
  children: ReactNode
}

export function Field({ label, htmlFor, error, hint, required, children }: FieldProps) {
  return (
    <div className="space-y-1">
      <label htmlFor={htmlFor} className="block text-sm font-medium text-slate-700">
        {label}
        {required && <span className="text-danger-600"> *</span>}
      </label>
      {children}
      {hint && !error && <p className="text-xs text-slate-400">{hint}</p>}
      {error && <p className="text-xs text-danger-600">{error}</p>}
    </div>
  )
}
