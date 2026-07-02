import type { ReactNode } from 'react'
import { ChevronRight } from 'lucide-react'
import { Link } from 'react-router-dom'

interface Crumb {
  label: string
  to: string
}

interface PageHeaderProps {
  title: string
  description?: ReactNode
  actions?: ReactNode
  breadcrumb?: Crumb[]
}

export function PageHeader({ title, description, actions, breadcrumb }: PageHeaderProps) {
  return (
    <div className="mb-6">
      {breadcrumb && breadcrumb.length > 0 && (
        <nav className="mb-2 flex items-center gap-1 text-xs text-slate-400">
          {breadcrumb.map((crumb) => (
            <span key={crumb.to} className="flex items-center gap-1">
              <Link to={crumb.to} className="transition-colors hover:text-brand-600">
                {crumb.label}
              </Link>
              <ChevronRight className="h-3 w-3" />
            </span>
          ))}
        </nav>
      )}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-slate-900">{title}</h1>
          {description && <div className="mt-1 text-sm text-slate-500">{description}</div>}
        </div>
        {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
      </div>
    </div>
  )
}
