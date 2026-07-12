import { useState } from 'react'
import type { KeyboardEvent } from 'react'
import { SendHorizonal, Loader2 } from 'lucide-react'
import { cn } from '../ui'

const MAX_LENGTH = 2000

/** The input row: Enter sends, Shift+Enter adds a line. */
export function ChatComposer({
  onSend,
  disabled: sending,
}: {
  onSend: (text: string) => void
  disabled: boolean
}) {
  const [text, setText] = useState('')
  const canSend = text.trim() !== '' && !sending

  const submit = () => {
    const trimmed = text.trim()
    if (trimmed === '' || sending) return
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
    <div className="px-5 pb-5 pt-2">
      <div className="flex items-end gap-2 rounded-2xl border border-white/10 bg-white/5 p-2 shadow-[0_0_24px_-6px_rgb(34_211_238_/0.15)] backdrop-blur-md transition-all focus-within:border-cyan-300/50 focus-within:shadow-[0_0_32px_-4px_rgb(34_211_238_/0.3)]">
        <textarea
          value={text}
          onChange={(e) => setText(e.target.value.slice(0, MAX_LENGTH))}
          onKeyDown={onKeyDown}
          rows={2}
          placeholder="Ask about stock, forecasts, reorders…"
          aria-label="Message the assistant"
          className="max-h-32 min-h-10 flex-1 resize-none rounded-xl bg-transparent px-3 py-2 text-sm text-cyan-50 placeholder:text-cyan-200/60 focus:outline-none disabled:opacity-60"
          disabled={sending}
        />
        <button
          type="button"
          onClick={submit}
          disabled={!canSend}
          aria-label="Send message"
          className={cn(
            'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/50 disabled:cursor-not-allowed disabled:opacity-50',
            canSend
              ? 'bg-gradient-to-br from-cyan-400 via-brand-500 to-fuchsia-500 shadow-[0_0_20px_-4px_rgb(34_211_238_/0.5)] hover:scale-110 hover:shadow-[0_0_28px_-2px_rgb(34_211_238_/0.6)] active:scale-95'
              : 'bg-slate-600/50',
          )}
        >
          {sending ? <Loader2 className="h-4 w-4 animate-spin" /> : <SendHorizonal className="h-4 w-4" />}
        </button>
      </div>
    </div>
  )
}
