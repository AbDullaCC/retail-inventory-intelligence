import { useCallback, useEffect, useState } from 'react'
import type { LucideIcon } from 'lucide-react'
import {
  Archive,
  ArrowUpRight,
  History,
  MessageSquarePlus,
  PackagePlus,
  TrendingUp,
  Wallet,
} from 'lucide-react'
import { chatApi } from '../../api/chat'
import { apiErrorMessage } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import { Button, cn, Skeleton } from '../ui'
import { AssistantGlyph } from './AssistantGlyph'
import { ChatComposer } from './ChatComposer'
import { MessageList } from './MessageList'
import type { ChatMessage, ChatThread } from '../../types'

const SUGGESTED_PROMPTS: Array<{ text: string; icon: LucideIcon }> = [
  { text: 'What should I reorder this week?', icon: PackagePlus },
  { text: 'How much cash is tied up in overstock?', icon: Wallet },
  { text: 'Any dead stock I should clear out?', icon: Archive },
  { text: 'How were sales in the last 30 days?', icon: TrendingUp },
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
    <div className="flex h-full flex-col">
      {/* Toolbar */}
      <div className="flex items-center justify-between gap-2 border-b border-slate-100 bg-white px-3 py-2">
        <Button variant="ghost" size="xs" onClick={newChat}>
          <MessageSquarePlus className="h-3.5 w-3.5" />
          New chat
        </Button>
        <Button
          variant="ghost"
          size="xs"
          onClick={() => setHistoryOpen((open) => !open)}
          aria-expanded={historyOpen}
          className={cn(historyOpen && 'bg-slate-100 text-slate-900')}
        >
          <History className="h-3.5 w-3.5" />
          Past conversations
        </Button>
      </div>

      {historyOpen ? (
        <div className="flex-1 overflow-y-auto px-3 py-3">
          {historyLoading ? (
            <div className="space-y-2 px-1">
              <Skeleton className="h-12 w-full rounded-xl" />
              <Skeleton className="h-12 w-full rounded-xl" />
            </div>
          ) : threads.length === 0 ? (
            <p className="px-2 py-4 text-sm text-slate-500">No conversations yet.</p>
          ) : (
            <ul className="space-y-1">
              {threads.map((t) => (
                <li key={t.id}>
                  <button
                    type="button"
                    onClick={() => void openThread(t.id)}
                    className={cn(
                      'w-full rounded-xl px-3 py-2.5 text-left transition-colors hover:bg-slate-50',
                      thread?.id === t.id && 'bg-brand-50/70 ring-1 ring-inset ring-brand-200',
                    )}
                  >
                    <span className="block truncate text-sm font-medium text-slate-800">
                      {t.title}
                    </span>
                    <span className="mt-0.5 block text-xs text-slate-400">
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
        <div className="chat-surface flex flex-1 flex-col items-center justify-center gap-5 overflow-y-auto px-6 py-8 text-center">
          <div className="relative">
            <span
              className="absolute -inset-3 rounded-full bg-brand-400/20 blur-lg"
              aria-hidden="true"
            />
            <AssistantGlyph
              className="relative h-12 w-12 rounded-2xl shadow-pop"
              iconClassName="h-5 w-5"
            />
          </div>
          <div>
            <p className="text-sm font-semibold text-slate-900">Ask me about your inventory</p>
            <p className="mt-1 text-xs leading-relaxed text-slate-500">
              Stock levels, demand forecasts, what to reorder — answers come from your live data.
            </p>
          </div>
          <div className="flex w-full max-w-xs flex-col gap-2">
            {SUGGESTED_PROMPTS.map(({ text, icon: Icon }, index) => (
              <button
                key={text}
                type="button"
                onClick={() => void send(text)}
                style={{ animationDelay: `${index * 60}ms`, animationFillMode: 'backwards' }}
                className="group flex w-full items-center gap-2.5 rounded-xl bg-white px-3.5 py-2.5 text-left text-sm text-slate-600 shadow-card ring-1 ring-slate-200/70 transition hover:text-slate-900 hover:shadow-pop hover:ring-brand-300 motion-safe:animate-msg-in motion-safe:hover:-translate-y-px"
              >
                <Icon className="h-4 w-4 shrink-0 text-brand-500" />
                <span className="flex-1">{text}</span>
                <ArrowUpRight className="h-3.5 w-3.5 shrink-0 text-slate-300 transition-colors group-hover:text-brand-500" />
              </button>
            ))}
          </div>
        </div>
      ) : (
        <MessageList messages={messages} thinking={sending} />
      )}

      {error && (
        <p className="border-t border-danger-100 bg-danger-50 px-4 py-2 text-xs text-danger-700" role="alert">
          {error}
        </p>
      )}

      <ChatComposer onSend={(text) => void send(text)} disabled={sending} />
    </div>
  )
}
