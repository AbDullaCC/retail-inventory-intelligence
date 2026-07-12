import { Suspense, lazy, useState } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { LayoutDashboard, Lightbulb, LogOut, Menu, Package, Plug, Sparkles, Tags } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { useAuth } from '../context/AuthContext'
import { Avatar, Drawer, Skeleton, Tooltip, cn } from './ui'
import { LogoWordmark } from './Logo'

// Lazy so catalog pages don't pay the chat bundle cost (same pattern as charts).
const ChatPanel = lazy(() =>
  import('./chat/ChatPanel').then((m) => ({ default: m.ChatPanel })),
)

interface NavItem {
  to: string
  label: string
  icon: LucideIcon
}

interface NavGroup {
  label: string
  items: NavItem[]
}

const navGroups: NavGroup[] = [
  {
    label: 'Overview',
    items: [
      { to: '/', label: 'Dashboard', icon: LayoutDashboard },
      { to: '/recommendations', label: 'Recommendations', icon: Lightbulb },
    ],
  },
  {
    label: 'Catalog',
    items: [
      { to: '/products', label: 'Products', icon: Package },
      { to: '/categories', label: 'Categories', icon: Tags },
    ],
  },
  {
    label: 'Settings',
    items: [{ to: '/integrations', label: 'Integrations', icon: Plug }],
  },
]

function NavGroups({ onNavigate }: { onNavigate?: () => void }) {
  return (
    <div className="space-y-6">
      {navGroups.map((group) => (
        <div key={group.label}>
          <p className="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
            {group.label}
          </p>
          <div className="space-y-0.5">
            {group.items.map(({ to, label, icon: Icon }) => (
              <NavLink
                key={to}
                to={to}
                end={to === '/'}
                onClick={onNavigate}
                className={({ isActive }) =>
                  cn(
                    'relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                    isActive
                      ? 'bg-brand-50 font-medium text-brand-700'
                      : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                  )
                }
              >
                {({ isActive }) => (
                  <>
                    {isActive && (
                      <span className="absolute inset-y-2 left-0 w-[3px] rounded-r bg-brand-600" />
                    )}
                    <Icon className="h-4 w-4" />
                    {label}
                  </>
                )}
              </NavLink>
            ))}
          </div>
        </div>
      ))}
    </div>
  )
}

function UserCard() {
  const { user, logout } = useAuth()

  return (
    <div className="flex items-center gap-3 border-t border-slate-100 px-4 py-3">
      <Avatar name={user?.name ?? '?'} />
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium text-slate-900">{user?.name}</p>
        <p className="truncate text-xs text-slate-400">{user?.email}</p>
      </div>
      <Tooltip content="Sign out">
        <button
          type="button"
          onClick={() => void logout()}
          className="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
          aria-label="Sign out"
        >
          <LogOut className="h-4 w-4" />
        </button>
      </Tooltip>
    </div>
  )
}

export function Layout() {
  const [mobileNavOpen, setMobileNavOpen] = useState(false)
  const [assistantOpen, setAssistantOpen] = useState(false)

  return (
    <div className="flex min-h-full">
      {/* Desktop sidebar */}
      <aside className="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-r border-slate-200 bg-white md:flex">
        <div className="px-5 py-5">
          <LogoWordmark />
        </div>
        <nav className="flex-1 overflow-y-auto px-3 pb-4">
          <NavGroups />
        </nav>
        <UserCard />
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        {/* Mobile top bar */}
        <header className="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 md:hidden">
          <LogoWordmark />
          <button
            type="button"
            onClick={() => setMobileNavOpen(true)}
            className="rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800"
            aria-label="Open navigation"
          >
            <Menu className="h-5 w-5" />
          </button>
        </header>

        <Drawer
          open={mobileNavOpen}
          onClose={() => setMobileNavOpen(false)}
          title="Menu"
          side="left"
          footer={<div className="w-full"><UserCard /></div>}
        >
          <NavGroups onNavigate={() => setMobileNavOpen(false)} />
        </Drawer>

        <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>

      {/* AI assistant: cosmic floating button → right-side drawer */}
      <div className="fixed bottom-6 right-6 z-40 inline-block transition-all duration-300 hover:scale-110 active:scale-95">
        <button
          type="button"
          onClick={() => setAssistantOpen(true)}
          className="relative flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-cyan-400 via-brand-500 to-fuchsia-500 text-white shadow-[0_0_40px_-6px_rgb(34_211_238_/0.5),0_0_60px_-12px_rgb(192_132_252_/0.35)] ring-[3px] ring-white/90 transition-all duration-300 hover:shadow-[0_0_60px_-6px_rgb(34_211_238_/0.7),0_0_80px_-12px_rgb(192_132_252_/0.5)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-cyan-400/50 animate-float group"
          aria-label="Open the AI assistant"
        >
          <span className="pointer-events-none absolute inset-[-28px] rounded-full border border-cyan-300/40 opacity-60 animate-orbit" style={{ clipPath: 'ellipse(48% 22% at 50% 50%)' }} />
          <span className="pointer-events-none absolute inset-[-36px] rounded-full border border-fuchsia-300/30 opacity-50 animate-counter-orbit" style={{ clipPath: 'ellipse(46% 18% at 50% 50%)' }} />
          <Sparkles className="relative h-6 w-6 animate-pulse" />
        </button>
      </div>

      <Drawer
        open={assistantOpen}
        onClose={() => setAssistantOpen(false)}
        title="Assistant"
        subtitle="Answers from your live inventory data"
        panelClassName="border-l border-cyan-500/30 bg-[linear-gradient(135deg,#0f172a_0%,#0b2c2a_40%,#1e1b4b_100%)]"
        headerClassName="border-b border-white/10 bg-gradient-to-r from-cyan-500/20 via-brand-500/20 to-fuchsia-500/20"
        contentClassName="scrollbar-cosmic"
        titleClassName="text-transparent bg-clip-text bg-gradient-to-r from-cyan-300 via-white to-fuchsia-300"
        subtitleClassName="text-cyan-100/70"
        closeButtonClassName="text-cyan-200 hover:bg-white/10 hover:text-white"
      >
        <Suspense
          fallback={
            <div className="space-y-3 rounded-xl">
              <Skeleton className="h-10 w-3/4 rounded-xl bg-white/10" />
              <Skeleton className="h-10 w-1/2 rounded-xl bg-white/10" />
            </div>
          }
        >
          <ChatPanel />
        </Suspense>
      </Drawer>
    </div>
  )
}
