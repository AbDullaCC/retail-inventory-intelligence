import { api } from '../lib/api'
import type { ApiItem, ApiList, ChatAnswer, ChatThread } from '../types'

export const chatApi = {
  threads: (): Promise<ChatThread[]> =>
    api.get<ApiList<ChatThread>>('/chat/threads').then((r) => r.data.data),

  thread: (id: number): Promise<ChatThread> =>
    api.get<ApiItem<ChatThread>>(`/chat/threads/${id}`).then((r) => r.data.data),

  /** Sends a message; omitting threadId starts a new conversation server-side. */
  send: (threadId: number | null, message: string): Promise<ChatAnswer> =>
    api
      .post<ApiItem<ChatAnswer>>('/chat/messages', { thread_id: threadId, message })
      .then((r) => r.data.data),
}
