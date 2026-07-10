import { useCallback, useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import { MessageSquarePlus, Sparkles } from 'lucide-react'
import { chatApi } from '../../api/chat'
import { apiErrorMessage } from '../../lib/api'
import type { ChatMessage, ChatThread } from '../../types'
import { Button, EmptyState } from '../ui'
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
 * The panel mounts lazily inside the Layout drawer so auth/catalog pages don't
 * pay the bundle cost.
 */
export function ChatPanel() {
  const [thread, setThread] = useState<ChatThread | null>(null)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Start a fresh thread without a backend round-trip; the first send creates
  // the real thread server-side and returns the persisted one.
  const newThread = useCallback(() => {
    setThread(null)
    setMessages([])
    setError(null)
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
    [thread],
  )

  // When the drawer reopens with an existing thread, nothing to fetch here —
  // messages are held in state. Kept for future thread-switch wiring (M4).
  useEffect(() => {
    return
  }, [])

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
