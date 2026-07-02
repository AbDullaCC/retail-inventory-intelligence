import type { ReactNode } from 'react'
import { History, TrendingUp, Wallet } from 'lucide-react'
import { APP_TAGLINE } from '../lib/brand'
import { LogoWordmark } from './Logo'

const valueProps = [
  {
    icon: TrendingUp,
    title: 'Model-driven forecasts',
    text: 'Per-product time-series models project demand, stockout dates and reorder points.',
  },
  {
    icon: Wallet,
    title: 'See the cash in your stock',
    text: 'Overstock, dead stock and recoverable cash surfaced automatically.',
  },
  {
    icon: History,
    title: 'Every unit accounted for',
    text: 'An immutable movement ledger backs every number on screen.',
  },
]

/** Split-screen chrome for the auth pages: brand panel left, form right. */
export function AuthShell({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-full">
      <div className="relative hidden w-[45%] flex-col justify-between overflow-hidden bg-brand-950 p-10 lg:flex">
        {/* Ambient glow + decorative demand curve */}
        <div className="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-brand-700/30 blur-3xl" />
        <div className="pointer-events-none absolute -bottom-24 -right-24 h-80 w-80 rounded-full bg-brand-800/40 blur-3xl" />
        <svg
          viewBox="0 0 400 160"
          className="pointer-events-none absolute bottom-0 left-0 w-full text-brand-400/20"
          preserveAspectRatio="none"
          aria-hidden="true"
        >
          <path
            d="M0,130 C60,120 90,80 140,90 C190,100 210,50 260,60 C310,70 340,20 400,30"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
          />
          <path
            d="M0,140 C70,135 110,100 160,108 C210,116 240,75 290,82 C340,89 370,50 400,55"
            fill="none"
            stroke="currentColor"
            strokeWidth="1"
            strokeDasharray="4 4"
          />
        </svg>

        <div className="relative">
          <LogoWordmark light />
        </div>

        <div className="relative max-w-md">
          <h1 className="text-3xl font-semibold leading-tight tracking-tight text-white">
            Know what to reorder before you run out.
          </h1>
          <ul className="mt-8 space-y-5">
            {valueProps.map(({ icon: Icon, title, text }) => (
              <li key={title} className="flex gap-3">
                <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-800/60 text-brand-200">
                  <Icon className="h-4 w-4" />
                </span>
                <div>
                  <p className="text-sm font-medium text-white">{title}</p>
                  <p className="mt-0.5 text-sm leading-relaxed text-brand-200/80">{text}</p>
                </div>
              </li>
            ))}
          </ul>
        </div>

        <p className="relative text-xs text-brand-300/60">{APP_TAGLINE}</p>
      </div>

      <div className="flex flex-1 items-center justify-center bg-slate-50 p-6">
        <div className="w-full max-w-sm">{children}</div>
      </div>
    </div>
  )
}
