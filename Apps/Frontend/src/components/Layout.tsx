import { useState } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { LayoutDashboard, Lightbulb, LogOut, Menu, Package, Tags } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { useAuth } from '../context/AuthContext'
import { Avatar, Drawer, Tooltip, cn } from './ui'
import { LogoWordmark } from './Logo'

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
    </div>
  )
}
