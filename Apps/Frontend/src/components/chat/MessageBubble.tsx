import type { ReactNode } from 'react'
import { Database } from 'lucide-react'
import { Tooltip } from '../ui'
import { AssistantGlyph } from './AssistantGlyph'
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
 * One chat bubble. Assistant replies carry a "Sources" rail — the read tools
 * consulted for the answer — so every number is traceable to live data.
 */
export function MessageBubble({ message }: { message: ChatMessage }) {
  const mine = message.role === 'user'

  if (mine) {
    return (
      <div className="flex justify-end motion-safe:animate-msg-in">
        <div className="max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-br-md bg-gradient-to-br from-brand-600 to-brand-700 px-3.5 py-2.5 text-sm leading-relaxed text-white shadow-card">
          {message.content}
        </div>
      </div>
    )
  }

  return (
    <div className="flex items-start gap-2 motion-safe:animate-msg-in">
      <AssistantGlyph className="mt-0.5 h-6 w-6" iconClassName="h-3 w-3" />
      <div className="flex min-w-0 max-w-[85%] flex-col items-start gap-1.5">
        <div className="whitespace-pre-wrap rounded-2xl rounded-tl-md bg-white px-3.5 py-2.5 text-sm leading-relaxed text-slate-800 shadow-card ring-1 ring-slate-200/70">
          {withBold(message.content)}
        </div>

        {message.tool_calls && message.tool_calls.length > 0 && (
          <div className="flex flex-wrap items-center gap-1 pl-1">
            <span className="text-[10px] font-medium uppercase tracking-wider text-slate-400">
              Sources
            </span>
            {message.tool_calls.map((call, index) => (
              <Tooltip key={`${call.name}-${index}`} content={call.summary}>
                <span className="inline-flex cursor-help items-center gap-1 rounded-full bg-white px-2 py-0.5 font-mono text-[10px] text-slate-500 ring-1 ring-inset ring-slate-200 transition-colors hover:text-slate-700 hover:ring-brand-300">
                  <Database className="h-2.5 w-2.5 text-brand-500" />
                  {call.name}
                </span>
              </Tooltip>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
