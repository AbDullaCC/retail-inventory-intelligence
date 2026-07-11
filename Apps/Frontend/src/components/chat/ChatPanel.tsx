import { useCallback, useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import { ChevronDown, ChevronRight, MessageSquare, MessageSquarePlus, Sparkles } from 'lucide-react'
import { chatApi } from '../../api/chat'
import { apiErrorMessage } from '../../lib/api'
import { formatShortDate } from '../../lib/format'
import type { ChatMessage, ChatThread } from '../../types'
import { Button, EmptyState, Skeleton, cn } from '../ui'
import { ChatComposer } from './ChatComposer'
import { MessageList } from './MessageList'
import { TypingIndicator } from './TypingIndicator'

const SUGGESTED_PROMPTS = [
  'What should I reorder this week?',
  'How much cash is tied up in dead stock?',
  "What's my upcoming demand for next month?",
  'Show me recent stock movements',
]

/**
 * Owns thread + message state for the chat drawer. v1: plain JSON send (no
 * streaming); the TypingIndicator stands in for the full round-trip.
 *
 * Past conversations load from GET /chat/threads and are listed in a
 * collapsible history section. Switching a thread fetches its messages
 * (GET /chat/threads/{id} → thread.messages).
 *
 * The panel mounts lazily inside the Layout drawer so auth/catalog pages don't
 * pay the bundle cost.
 */
export function ChatPanel() {
  const [thread, setThread] = useState<ChatThread | null>(null)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [threads, setThreads] = useState<ChatThread[]>([])
  const [threadsLoading, setThreadsLoading] = useState(true)
  const [historyOpen, setHistoryOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Load the user's past threads on mount so history is visible immediately.
  const loadThreads = useCallback(async () => {
    setThreadsLoading(true)
    try {
      setThreads(await chatApi.getThreads())
    } catch {
      // Non-fatal: the user can still start a new chat. Don't spam a toast.
      setThreads([])
    } finally {
      setThreadsLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadThreads()
  }, [loadThreads])

  // Start a fresh thread without a backend round-trip; the first send creates
  // the real thread server-side and returns the persisted one.
  const newThread = useCallback(() => {
    setThread(null)
    setMessages([])
    setError(null)
  }, [])

  // Open an existing thread: fetch its messages and swap it in.
  const openThread = useCallback(async (id: number) => {
    setLoading(true)
    setError(null)
    try {
      const fetched = await chatApi.getThread(id)
      setThread(fetched)
      setMessages(fetched.messages ?? [])
      setHistoryOpen(false)
    } catch (err) {
      setError(apiErrorMessage(err, 'Could not open that conversation.'))
    } finally {
      setLoading(false)
    }
  }, [])

  const send = useCallback(
    async (text: string) => {
      setLoading(true)
      setError(null)

      // Optimistically show the user's message immediately.
      const optimistic: ChatMessage = {
        id: -Date.now(),
        thread_id: thread?.id ?? -1,
        role: 'user',
        content: text,
        tool_calls: null,
        created_at: new Date().toISOString(),
      }
      setMessages((prev) => [...prev, optimistic])

      try {
        const answer = await chatApi.sendMessage(thread?.id ?? null, text)
        // Replace the optimistic user message with the persisted pair. The
        // assistant reply is the authoritative record; the user message is
        // rebuilt from the server so ids/timestamps are real.
        setThread(answer.thread)
        setMessages((prev) => [
          ...prev.filter((m) => m.id !== optimistic.id),
          { ...optimistic, id: optimistic.id, thread_id: answer.thread.id },
          answer.message,
        ])
        // A new thread was created server-side — refresh the history list so it
        // shows up, and bump its last-message time for ordering.
        void loadThreads()
      } catch (err) {
        // Drop the optimistic bubble on failure so the user can retry cleanly.
        setMessages((prev) => prev.filter((m) => m.id !== optimistic.id))

        const status = (err as { response?: { status?: number } }).response?.status
        if (status === 429) {
          toast.error("You've sent too many messages this hour. Please try again later.")
        } else {
          // The backend surfaces the real Gemini error verbatim in `message`
          // (e.g. "Gemini API error (HTTP 503): This model is currently
          // experiencing high demand…"). Show that — it's the actual cause.
          setError(apiErrorMessage(err, 'The assistant is unavailable right now. Please try again.'))
        }
      } finally {
        setLoading(false)
      }
    },
    [thread, loadThreads],
  )

  return (
    <div className="flex h-full flex-col">
      {/* Header strip: current thread title + new-chat action */}
      <div className="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
        <span className="truncate text-xs font-medium text-slate-600">
          {thread ? thread.title : 'New chat'}
        </span>
        <Button variant="ghost" size="xs" onClick={newThread} disabled={loading}>
          <MessageSquarePlus className="h-3.5 w-3.5" />
          New
        </Button>
      </div>

      <div className="flex-1 overflow-y-auto px-3">
        {/* History — collapsible list of past conversations */}
        <div className="py-2">
          <button
            type="button"
            onClick={() => setHistoryOpen((v) => !v)}
            className="flex w-full items-center gap-1 rounded-md px-1 py-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400 transition-colors hover:text-slate-600"
            aria-expanded={historyOpen}
          >
            {historyOpen ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
            History
            {threads.length > 0 && <span className="font-normal normal-case text-slate-300">({threads.length})</span>}
          </button>

          {historyOpen && (
            <div className="mt-1 space-y-0.5">
              {threadsLoading ? (
                <Skeleton className="h-8 w-full" />
              ) : threads.length === 0 ? (
                <p className="px-2 py-2 text-xs text-slate-400">No conversations yet.</p>
              ) : (
                threads.map((t) => (
                  <button
                    key={t.id}
                    type="button"
                    onClick={() => void openThread(t.id)}
                    disabled={loading}
                    className={cn(
                      'flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs transition-colors disabled:opacity-50',
                      thread?.id === t.id
                        ? 'bg-brand-50 font-medium text-brand-700'
                        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                    )}
                  >
                    <MessageSquare className="h-3.5 w-3.5 shrink-0 opacity-60" />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate">{t.title || 'New chat'}</span>
                      <span className="block text-[10px] text-slate-400">
                        {t.message_count} {t.message_count === 1 ? 'message' : 'messages'}
                        {t.last_message_at ? ` · ${formatShortDate(t.last_message_at)}` : ''}
                      </span>
                    </span>
                  </button>
                ))
              )}
            </div>
          )}
        </div>

        {messages.length === 0 && !loading ? (
          <EmptyState
            illustration={<Sparkles className="h-8 w-8" />}
            title="Ask Shelfwise anything"
            message="I can summarise your stock, forecasts, and reorder priorities — read-only, sourced from the same services the dashboard uses."
            action={
              <div className="flex flex-col gap-1.5">
                {SUGGESTED_PROMPTS.map((prompt) => (
                  <Button
                    key={prompt}
                    variant="secondary"
                    size="sm"
                    onClick={() => void send(prompt)}
                    className="justify-start text-left"
                  >
                    {prompt}
                  </Button>
                ))}
              </div>
            }
          />
        ) : (
          <MessageList messages={messages} footer={loading ? <TypingIndicator /> : undefined} />
        )}

        {error && (
          <div className="mb-2 rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-xs text-danger-700">
            {error}
          </div>
        )}
      </div>

      <ChatComposer disabled={loading} onSend={(text) => void send(text)} />
    </div>
  )
}
