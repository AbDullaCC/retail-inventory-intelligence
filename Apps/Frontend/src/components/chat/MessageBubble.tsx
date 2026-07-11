import type { ReactNode } from 'react'
import { Database } from 'lucide-react'
import { cn } from '../ui'
import { Tooltip } from '../ui'
import type { ChatMessage } from '../../types'

/**
 * The one markdown habit LLMs never drop is **bold** — render it, leave
 * everything else as plain text (no markdown library on the chat path).
 */
function withBold(text: string): ReactNode[] {
  return text.split(/\*\*(.+?)\*\*/g).map((part, index) =>
    index % 2 === 1 ? <strong key={index}>{part}</strong> : part,
  )
}

/**
 * One chat bubble. Assistant replies carry "sources" chips — the read tools
 * consulted for the answer — so every number is traceable.
 */
export function MessageBubble({ message }: { message: ChatMessage }) {
  const mine = message.role === 'user'

  return (
    <div className={cn('flex flex-col gap-1', mine ? 'items-end' : 'items-start')}>
      <div
        className={cn(
          'max-w-[85%] whitespace-pre-wrap rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed',
          mine
            ? 'rounded-br-sm bg-brand-600 text-white'
            : 'rounded-bl-sm bg-slate-100 text-slate-800',
        )}
      >
        {mine ? message.content : withBold(message.content)}
      </div>

      {!mine && message.tool_calls && message.tool_calls.length > 0 && (
        <div className="flex max-w-[85%] flex-wrap items-center gap-1">
          {message.tool_calls.map((call, index) => (
            <Tooltip key={`${call.name}-${index}`} content={call.summary}>
              <span className="inline-flex cursor-help items-center gap-1 rounded-full bg-slate-50 px-2 py-0.5 font-mono text-[10px] text-slate-500 ring-1 ring-inset ring-slate-200">
                <Database className="h-2.5 w-2.5" />
                {call.name}
              </span>
            </Tooltip>
          ))}
        </div>
      )}
    </div>
  )
}
