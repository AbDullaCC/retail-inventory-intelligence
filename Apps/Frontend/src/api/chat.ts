import { api } from '../lib/api'
import type { ApiItem, ApiList, ChatAnswer, ChatThread } from '../types'

// v1: plain axios POST (no streaming). Streaming lands in M5 — see
// docs/AI-Chatbot-Implementation-Plan.md §8.
export const chatApi = {
  getThreads: (): Promise<ChatThread[]> =>
    api.get<ApiList<ChatThread>>('/chat/threads').then((r) => r.data.data),

  getThread: (id: number): Promise<ChatThread> =>
    api.get<ApiItem<ChatThread>>(`/chat/threads/${id}`).then((r) => r.data.data),

  createThread: (title?: string): Promise<ChatThread> =>
    api.post<ApiItem<ChatThread>>('/chat/threads', { title: title ?? null }).then((r) => r.data.data),

  // Sends a message, optionally into an existing thread (creates one when
  // threadId is omitted — the backend keeps the send path single + explicit).
  sendMessage: (threadId: number | null, message: string): Promise<ChatAnswer> =>
    api
      .post<ApiItem<ChatAnswer>>('/chat/messages', {
        thread_id: threadId,
        message,
      })
      .then((r) => r.data.data),
}
