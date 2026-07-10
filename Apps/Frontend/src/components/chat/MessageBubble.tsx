import { Wrench } from 'lucide-react'
import type { ChatMessage } from '../../types'
import { Avatar, Badge, cn } from '../ui'

/** Human-readable label for a tool name (snake_case → title case). */
function toolLabel(name: string): string {
  return name
    .split('_')
    .filter(Boolean)
    .map((w) => w[0]!.toUpperCase() + w.slice(1))
    .join(' ')
}

function formatTime(iso: string): string {
  const d = new Date(iso)
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

export function MessageBubble({ message }: { message: ChatMessage }) {
  const isUser = message.role === 'user'

  return (
    <div className={cn('flex gap-2.5', isUser ? 'flex-row-reverse' : 'flex-row')}>
      <Avatar name={isUser ? 'You' : 'SW'} className={cn(isUser ? 'bg-slate-100 text-slate-600' : 'bg-brand-100 text-brand-700')} />

      <div className={cn('flex max-w-[80%] flex-col gap-1.5', isUser ? 'items-end' : 'items-start')}>
        <div
          className={cn(
            'rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed whitespace-pre-wrap break-words',
            isUser ? 'bg-brand-600 text-white' : 'border border-slate-200 bg-white text-slate-800',
          )}
        >
          {message.content}
        </div>

        {/* Cited read-tools — never the full payload, only the result_summary. */}
        {message.tool_calls && message.tool_calls.length > 0 && (
          <div className="flex flex-wrap gap-1.5">
            {message.tool_calls.map((call, i) => (
              <span
                key={`${call.name}-${i}`}
                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] text-slate-500"
                title={call.result_summary}
              >
                <Wrench className="h-3 w-3 text-brand-500" />
                <Badge tone="indigo">{toolLabel(call.name)}</Badge>
              </span>
            ))}
          </div>
        )}

        <time className="px-1 text-[10px] text-slate-400">{formatTime(message.created_at)}</time>
      </div>
    </div>
  )
}
