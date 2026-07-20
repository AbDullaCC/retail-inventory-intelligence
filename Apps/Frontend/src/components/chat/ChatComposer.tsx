import { useRef, useState } from 'react'
import type { KeyboardEvent } from 'react'
import { ArrowUp } from 'lucide-react'

const MAX_LENGTH = 2000
const MAX_HEIGHT_PX = 128

/** The input row: Enter sends, Shift+Enter adds a line. */
export function ChatComposer({
  onSend,
  disabled,
}: {
  onSend: (text: string) => void
  disabled: boolean
}) {
  const [text, setText] = useState('')
  const areaRef = useRef<HTMLTextAreaElement>(null)

  /** Grow with the content up to a cap, then let the textarea scroll. */
  const autosize = () => {
    const el = areaRef.current
    if (!el) return
    el.style.height = 'auto'
    if (el.scrollHeight > 0) el.style.height = `${Math.min(el.scrollHeight, MAX_HEIGHT_PX)}px`
  }

  const submit = () => {
    const trimmed = text.trim()
    if (trimmed === '' || disabled) return
    setText('')
    if (areaRef.current) areaRef.current.style.height = 'auto'
    onSend(trimmed)
  }

  const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault()
      submit()
    }
  }

  return (
    <div className="border-t border-slate-100 bg-white px-3 pb-2.5 pt-3">
      <div className="flex items-end gap-1.5 rounded-2xl bg-slate-50 p-1.5 ring-1 ring-slate-200 transition-shadow focus-within:bg-white focus-within:ring-2 focus-within:ring-brand-500/50">
        <textarea
          ref={areaRef}
          value={text}
          onChange={(e) => {
            setText(e.target.value.slice(0, MAX_LENGTH))
            autosize()
          }}
          onKeyDown={onKeyDown}
          rows={1}
          placeholder="Ask about stock, forecasts, reorders…"
          aria-label="Message the assistant"
          className="min-h-9 flex-1 resize-none bg-transparent px-2.5 py-1.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none disabled:opacity-60"
          disabled={disabled}
        />
        <button
          type="button"
          onClick={submit}
          disabled={disabled || text.trim() === ''}
          aria-label="Send message"
          className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-white shadow-card transition-colors hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none"
        >
          <ArrowUp className="h-4 w-4" />
        </button>
      </div>
      <p className="mt-1.5 px-2 text-[10px] text-slate-400">
        Enter to send · Shift+Enter for a new line
      </p>
    </div>
  )
}
