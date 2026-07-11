import { useState } from 'react'
import type { KeyboardEvent } from 'react'
import { SendHorizonal } from 'lucide-react'
import { Button } from '../ui'

const MAX_LENGTH = 2000

/** The input row: Enter sends, Shift+Enter adds a line. */
export function ChatComposer({
  onSend,
  disabled,
}: {
  onSend: (text: string) => void
  disabled: boolean
}) {
  const [text, setText] = useState('')

  const submit = () => {
    const trimmed = text.trim()
    if (trimmed === '' || disabled) return
    setText('')
    onSend(trimmed)
  }

  const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault()
      submit()
    }
  }

  return (
    <div className="flex items-end gap-2 border-t border-slate-100 px-4 py-3">
      <textarea
        value={text}
        onChange={(e) => setText(e.target.value.slice(0, MAX_LENGTH))}
        onKeyDown={onKeyDown}
        rows={2}
        placeholder="Ask about stock, forecasts, reorders…"
        aria-label="Message the assistant"
        className="min-h-10 flex-1 resize-none rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
        disabled={disabled}
      />
      <Button size="sm" onClick={submit} disabled={disabled || text.trim() === ''} aria-label="Send message">
        <SendHorizonal className="h-4 w-4" />
      </Button>
    </div>
  )
}
