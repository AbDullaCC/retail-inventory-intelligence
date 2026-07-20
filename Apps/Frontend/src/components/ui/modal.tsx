import { useEffect } from 'react'
import type { ReactNode } from 'react'
import { X } from 'lucide-react'
import { cn } from './cn'
import { Button } from './button'

function useOverlay(open: boolean, onClose: () => void) {
  useEffect(() => {
    if (!open) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    document.body.style.overflow = 'hidden'
    return () => {
      window.removeEventListener('keydown', onKey)
      document.body.style.overflow = ''
    }
  }, [open, onClose])
}

interface ModalProps {
  open: boolean
  onClose: () => void
  title: string
  children: ReactNode
  footer?: ReactNode
  size?: 'md' | 'lg'
}

export function Modal({ open, onClose, title, children, footer, size = 'md' }: ModalProps) {
  useOverlay(open, onClose)

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/45 p-4 backdrop-blur-[2px] transition-opacity duration-150 starting:opacity-0 sm:items-center">
      <div className="absolute inset-0" onClick={onClose} aria-hidden="true" />
      <div
        className={cn(
          'relative z-10 w-full rounded-2xl bg-white shadow-pop transition duration-150 starting:scale-95 starting:opacity-0',
          size === 'lg' ? 'max-w-2xl' : 'max-w-lg',
        )}
      >
        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <h2 className="text-lg font-semibold tracking-tight text-slate-900">{title}</h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-md p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
            aria-label="Close"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        <div className="px-5 py-4">{children}</div>
        {footer && (
          <div className="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">{footer}</div>
        )}
      </div>
    </div>
  )
}

interface DrawerProps {
  open: boolean
  onClose: () => void
  /** Plain-text header; ignored when `header` is provided. */
  title?: string
  subtitle?: string
  /** Custom header content — replaces title/subtitle; the close button stays. */
  header?: ReactNode
  children: ReactNode
  footer?: ReactNode
  /** Set false to hand the children the full, unscrolled column (e.g. chat). */
  padded?: boolean
  /** Which edge the panel slides from. */
  side?: 'right' | 'left'
}

export function Drawer({
  open,
  onClose,
  title,
  subtitle,
  header,
  children,
  footer,
  padded = true,
  side = 'right',
}: DrawerProps) {
  useOverlay(open, onClose)

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/45 backdrop-blur-[2px] transition-opacity duration-200 starting:opacity-0">
      <div className="absolute inset-0" onClick={onClose} aria-hidden="true" />
      <div
        className={cn(
          'absolute inset-y-0 flex w-full max-w-md flex-col bg-white shadow-pop transition-transform duration-200',
          side === 'right' ? 'right-0 starting:translate-x-full' : 'left-0 starting:-translate-x-full',
        )}
      >
        <div className="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
          {header ?? (
            <div>
              <h2 className="text-lg font-semibold tracking-tight text-slate-900">{title}</h2>
              {subtitle && <p className="mt-0.5 text-xs text-slate-500">{subtitle}</p>}
            </div>
          )}
          <button
            type="button"
            onClick={onClose}
            className="rounded-md p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
            aria-label="Close"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        <div className={cn('flex-1', padded ? 'overflow-y-auto px-5 py-4' : 'overflow-hidden')}>
          {children}
        </div>
        {footer && (
          <div className="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">{footer}</div>
        )}
      </div>
    </div>
  )
}

interface ConfirmDialogProps {
  open: boolean
  onClose: () => void
  onConfirm: () => void
  title: string
  message: string
  confirmLabel?: string
  loading?: boolean
}

export function ConfirmDialog({
  open,
  onClose,
  onConfirm,
  title,
  message,
  confirmLabel = 'Delete',
  loading = false,
}: ConfirmDialogProps) {
  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={loading}>
            Cancel
          </Button>
          <Button variant="danger" onClick={onConfirm} loading={loading}>
            {confirmLabel}
          </Button>
        </>
      }
    >
      <p className="text-sm text-slate-600">{message}</p>
    </Modal>
  )
}
