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
    <div className={cn('flex flex-col gap-1 animate-message-in', mine ? 'items-end' : 'items-start')}>
      <div
        className={cn(
          'max-w-[85%] whitespace-pre-wrap rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed shadow-[0_0_20px_-6px_rgba(0,0,0,0.3)]',
          mine
            ? 'rounded-br-sm border border-cyan-300/40 bg-gradient-to-br from-cyan-500 to-brand-600 text-white shadow-[0_0_24px_-6px_rgb(34_211_238_/0.35)]'
            : 'rounded-bl-sm border border-white/10 bg-white/10 text-cyan-50 backdrop-blur-md',
        )}
      >
        {mine ? message.content : withBold(message.content)}
      </div>

      {!mine && message.tool_calls && message.tool_calls.length > 0 && (
        <div className="flex max-w-[85%] flex-wrap items-center gap-1">
          {message.tool_calls.map((call, index) => (
            <Tooltip key={`${call.name}-${index}`} content={call.summary}>
              <span className="inline-flex cursor-help items-center gap-1.5 rounded-full border border-cyan-300/30 bg-black/20 px-2.5 py-1 font-mono text-[10px] text-cyan-200 shadow-[0_0_12px_-2px_rgb(34_211_238_/0.15)] backdrop-blur-sm transition-all hover:border-cyan-300/60 hover:text-cyan-300 hover:shadow-[0_0_16px_-2px_rgb(34_211_238_/0.3)]">
                <Database className="h-2.5 w-2.5 text-cyan-400" />
                {call.name}
              </span>
            </Tooltip>
          ))}
        </div>
      )}
    </div>
  )
}
