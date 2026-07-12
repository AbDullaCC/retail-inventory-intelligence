import { useCallback, useEffect, useState } from 'react'
import { History, MessageSquarePlus, Sparkles } from 'lucide-react'
import { chatApi } from '../../api/chat'
import { apiErrorMessage } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import { cn, Skeleton, Tooltip } from '../ui'
import { ChatComposer } from './ChatComposer'
import { MessageList } from './MessageList'
import type { ChatMessage, ChatThread } from '../../types'

const SUGGESTED_PROMPTS = [
  'What should I reorder this week?',
  'How much cash is tied up in overstock?',
  'Any dead stock I should clear out?',
  'How were sales in the last 30 days?',
]

/**
 * The assistant drawer's content: current conversation, a history list of
 * past threads, suggested prompts, and the composer. v1 is non-streaming —
 * the typing indicator covers the round-trip.
 */
export function ChatPanel() {
  const [thread, setThread] = useState<ChatThread | null>(null)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [threads, setThreads] = useState<ChatThread[]>([])
  const [historyOpen, setHistoryOpen] = useState(false)
  const [historyLoading, setHistoryLoading] = useState(false)
  const [sending, setSending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const loadThreads = useCallback(async () => {
    setHistoryLoading(true)
    try {
      setThreads(await chatApi.threads())
    } catch {
      setThreads([]) // non-fatal: a new chat still works
    } finally {
      setHistoryLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadThreads()
  }, [loadThreads])

  const newChat = () => {
    setThread(null)
    setMessages([])
    setError(null)
    setHistoryOpen(false)
  }

  const openThread = async (id: number) => {
    setError(null)
    setHistoryOpen(false)
    setSending(true)
    try {
      const fetched = await chatApi.thread(id)
      setThread(fetched)
      setMessages(fetched.messages ?? [])
    } catch (err) {
      setError(apiErrorMessage(err))
    } finally {
      setSending(false)
    }
  }

  const send = async (text: string) => {
    setSending(true)
    setError(null)

    // Show the user's message immediately; negative id marks it optimistic.
    const optimistic: ChatMessage = {
      id: -Date.now(),
      thread_id: thread?.id ?? 0,
      role: 'user',
      content: text,
      tool_calls: null,
      created_at: new Date().toISOString(),
    }
    setMessages((prev) => [...prev, optimistic])

    try {
      const answer = await chatApi.send(thread?.id ?? null, text)
      setThread(answer.thread)
      setMessages((prev) => [
        ...prev.map((m) => (m.id === optimistic.id ? { ...m, thread_id: answer.thread.id } : m)),
        answer.message,
      ])
      void loadThreads() // a new thread may have been created
    } catch (err) {
      // Withdraw the optimistic bubble so a retry doesn't duplicate it.
      setMessages((prev) => prev.filter((m) => m.id !== optimistic.id))
      setError(apiErrorMessage(err))
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="-mx-5 -my-4 flex h-full flex-col text-white">
      {/* Toolbar */}
      <div className="flex items-center justify-between gap-2 px-5 pt-4 pb-3">
        <div className="inline-flex items-center gap-1 rounded-full border border-cyan-300/30 bg-white/5 px-1.5 py-1 shadow-[0_0_20px_-4px_rgb(34_211_238_/0.2)] backdrop-blur-md">
          <Tooltip content="Start a new conversation">
            <button
              type="button"
              onClick={newChat}
              className="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-medium text-cyan-100 transition-all hover:bg-cyan-400/15 hover:text-cyan-300 hover:shadow-[0_0_12px_-2px_rgb(34_211_238_/0.4)]"
            >
              <MessageSquarePlus className="h-3.5 w-3.5" />
              New chat
            </button>
          </Tooltip>
          <span className="h-3.5 w-px bg-cyan-300/30" />
          <Tooltip content="Browse past conversations">
            <button
              type="button"
              onClick={() => setHistoryOpen((open) => !open)}
              aria-expanded={historyOpen}
              className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-medium transition-all',
                historyOpen
                  ? 'bg-cyan-400/20 text-cyan-300 shadow-[0_0_12px_-2px_rgb(34_211_238_/0.4)]'
                  : 'text-cyan-100 hover:bg-cyan-400/15 hover:text-cyan-300 hover:shadow-[0_0_12px_-2px_rgb(34_211_238_/0.4)]',
              )}
            >
              <History className="h-3.5 w-3.5" />
              Past conversations
            </button>
          </Tooltip>
        </div>
      </div>

      {historyOpen ? (
        <div className="scrollbar-cosmic flex-1 overflow-y-auto px-5 py-2">
          {historyLoading ? (
            <div className="space-y-2 px-1">
              <Skeleton className="h-12 w-full rounded-xl bg-white/10" />
              <Skeleton className="h-12 w-full rounded-xl bg-white/10" />
            </div>
          ) : threads.length === 0 ? (
            <div className="flex flex-col items-center justify-center gap-3 py-12 text-center">
              <div className="relative flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-cyan-400/20 to-fuchsia-500/20 text-cyan-300 ring-1 ring-cyan-300/30 shadow-[0_0_20px_-4px_rgb(34_211_238_/0.3)]">
                <History className="h-6 w-6" />
                <span className="absolute inset-[-4px] rounded-full border border-cyan-300/20 animate-ring-pulse" />
              </div>
              <p className="text-sm text-cyan-100/80">No conversations yet.</p>
            </div>
          ) : (
            <ul className="space-y-2">
              {threads.map((t) => (
                <li key={t.id}>
                  <button
                    type="button"
                    onClick={() => void openThread(t.id)}
                    className={cn(
                      'group relative w-full overflow-hidden rounded-xl border px-4 py-3 text-left shadow-[0_0_20px_-6px_rgb(34_211_238_/0.1)] transition-all duration-200 hover:-translate-y-px hover:border-cyan-300/60 hover:shadow-[0_0_28px_-4px_rgb(34_211_238_/0.25)]',
                      thread?.id === t.id
                        ? 'border-cyan-300/60 bg-gradient-to-r from-cyan-500/15 to-fuchsia-500/15'
                        : 'border-white/10 bg-white/5',
                    )}
                  >
                    <span className="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-cyan-400 to-fuchsia-400 opacity-0 transition-opacity group-hover:opacity-100" />
                    <span className={cn('block truncate text-sm font-medium', thread?.id === t.id ? 'text-cyan-200' : 'text-cyan-50')}>
                      {t.title}
                    </span>
                    <span className="block text-xs text-cyan-200/70">
                      {t.message_count} messages · {formatDateTime(t.last_message_at)}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      ) : messages.length === 0 && !sending ? (
        /* Empty state with suggested prompts */
        <div className="relative flex flex-1 flex-col items-center justify-center gap-5 px-6 pb-6 text-center overflow-hidden">
          <div className="pointer-events-none absolute inset-0 opacity-40">
            <div className="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-cyan-400/20 blur-[80px] animate-nebula-drift" />
            <div className="absolute -left-20 bottom-10 h-56 w-56 rounded-full bg-fuchsia-500/15 blur-[70px] animate-nebula-drift" style={{ animationDelay: '3s' }} />
          </div>

          <div className="relative flex h-24 w-24 items-center justify-center">
            <span className="absolute inset-[-12px] rounded-full border border-cyan-300/30 animate-orbit" style={{ clipPath: 'ellipse(50% 24% at 50% 50%)' }} />
            <span className="absolute inset-[-20px] rounded-full border border-fuchsia-300/25 animate-counter-orbit" style={{ clipPath: 'ellipse(48% 18% at 50% 50%)' }} />
            <span className="absolute inset-0 rounded-full bg-gradient-to-br from-cyan-400 via-brand-500 to-fuchsia-500 opacity-60 blur-xl animate-cosmic-glow" />
            <div className="relative flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 via-brand-500 to-fuchsia-500 text-white shadow-[0_0_40px_-6px_rgb(34_211_238_/0.5),0_0_60px_-12px_rgb(192_132_252_/0.35)] ring-[3px] ring-white/20 animate-float">
              <Sparkles className="h-7 w-7 animate-pulse" />
            </div>
          </div>

          <div className="relative">
            <p className="bg-gradient-to-r from-cyan-300 via-white to-fuchsia-300 bg-clip-text text-xl font-bold tracking-tight text-transparent drop-shadow-[0_0_10px_rgba(34,211,238,0.3)]">
              Ask me about your inventory
            </p>
            <p className="mx-auto mt-1 max-w-[260px] text-xs leading-relaxed text-cyan-100/70">
              Stock levels, demand forecasts, what to reorder — answers come from your live data.
            </p>
          </div>

          <div className="relative flex w-full max-w-sm flex-col gap-2.5">
            {SUGGESTED_PROMPTS.map((prompt) => (
              <button
                key={prompt}
                type="button"
                onClick={() => void send(prompt)}
                className="group relative overflow-hidden rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-left text-sm text-cyan-50 shadow-[0_0_16px_-6px_rgb(34_211_238_/0.1)] transition-all duration-200 hover:-translate-y-0.5 hover:border-cyan-300/50 hover:bg-white/10 hover:shadow-[0_0_28px_-4px_rgb(34_211_238_/0.25)]"
              >
                <span className="absolute inset-0 bg-gradient-to-r from-cyan-400/0 via-cyan-400/10 to-fuchsia-400/0 opacity-0 transition-opacity group-hover:opacity-100 animate-shimmer" />
                <span className="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-cyan-400 to-fuchsia-400 opacity-0 transition-opacity group-hover:opacity-100" />
                <span className="relative">{prompt}</span>
              </button>
            ))}
          </div>
        </div>
      ) : (
        <MessageList messages={messages} thinking={sending} />
      )}

      {error && (
        <div className="mx-5 mb-2 rounded-lg border border-rose-400/50 bg-rose-950/60 px-4 py-2.5 text-xs text-rose-200 shadow-[0_0_20px_-4px_rgb(244_63_94_/0.3)] backdrop-blur-md" role="alert">
          {error}
        </div>
      )}

      <ChatComposer onSend={(text) => void send(text)} disabled={sending} />
    </div>
  )
}
