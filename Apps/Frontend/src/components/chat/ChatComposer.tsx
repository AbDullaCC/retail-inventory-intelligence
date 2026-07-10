import { useState, type KeyboardEvent } from 'react'
import { Send } from 'lucide-react'
import { Button, Textarea } from '../ui'

/**
 * Multi-line composer. Enter sends, Shift+Enter inserts a newline. Disabled
 * while awaiting a response so a user can't queue duplicate sends.
 */
export function ChatComposer({
  disabled,
  onSend,
}: {
  disabled: boolean
  onSend: (text: string) => void
}) {
  const [value, setValue] = useState('')

  const submit = () => {
    const text = value.trim()
    if (!text || disabled) return
    onSend(text)
    setValue('')
  }

  const onKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      submit()
    }
  }

  return (
    <div className="flex items-end gap-2 border-t border-slate-100 p-3">
      <Textarea
        value={value}
        onChange={(e) => setValue(e.target.value)}
        onKeyDown={onKeyDown}
        disabled={disabled}
        rows={1}
        placeholder="Ask about stock, forecasts, or reorders…"
        className="max-h-40 min-h-[40px] resize-none"
        aria-label="Message"
      />
      <Button onClick={submit} disabled={disabled || value.trim() === ''} size="md" aria-label="Send message">
        <Send className="h-4 w-4" />
      </Button>
    </div>
  )
}
