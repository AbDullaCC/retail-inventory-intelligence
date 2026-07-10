import { describe, it, expect, vi, beforeEach } from 'vitest'

// Mock the shared axios instance so the chat API maps requests/responses
// without touching the network.
vi.mock('../lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

import { api } from '../lib/api'
import { chatApi } from './chat'
import type { ApiItem, ChatAnswer, ChatThread } from '../types'

const mockedGet = vi.mocked(api.get)
const mockedPost = vi.mocked(api.post)

function thread(overrides: Partial<ChatThread> = {}): ChatThread {
  return {
    id: 1,
    user_id: 7,
    title: 'New chat',
    message_count: 0,
    last_message_at: null,
    created_at: '2026-07-10T10:00:00Z',
    messages: null,
    ...overrides,
  }
}

describe('chatApi', () => {
  beforeEach(() => {
    mockedGet.mockReset()
    mockedPost.mockReset()
  })

  it('getThreads unwraps the ApiList envelope', async () => {
    const list = [thread({ id: 1 }), thread({ id: 2, title: 'second' })]
    mockedGet.mockResolvedValueOnce({ data: { data: list } } as never)

    await expect(chatApi.getThreads()).resolves.toEqual(list)
    expect(mockedGet).toHaveBeenCalledWith('/chat/threads')
  })

  it('getThread unwraps the ApiItem envelope', async () => {
    const t = thread({ id: 5, messages: [] })
    mockedGet.mockResolvedValueOnce({ data: { data: t } } as never)

    await expect(chatApi.getThread(5)).resolves.toEqual(t)
    expect(mockedGet).toHaveBeenCalledWith('/chat/threads/5')
  })

  it('createThread posts a nullable title', async () => {
    const t = thread()
    mockedPost.mockResolvedValue({ data: { data: t } as ApiItem<ChatThread> } as never)

    await chatApi.createThread('Reorders')
    expect(mockedPost).toHaveBeenCalledWith('/chat/threads', { title: 'Reorders' })

    await chatApi.createThread()
    expect(mockedPost).toHaveBeenLastCalledWith('/chat/threads', { title: null })
  })

  it('sendMessage posts thread_id + message and unwraps the answer', async () => {
    const answer: ChatAnswer = {
      thread: thread({ id: 9, message_count: 2 }),
      message: {
        id: 3,
        thread_id: 9,
        role: 'assistant',
        content: 'Reorder Cola.',
        tool_calls: [{ name: 'get_reorder_recommendations', args: {}, result_summary: '2 items' }],
        created_at: '2026-07-10T10:01:00Z',
      },
    }
    mockedPost.mockResolvedValueOnce({ data: { data: answer } as ApiItem<ChatAnswer> } as never)

    await expect(chatApi.sendMessage(9, 'What should I reorder?')).resolves.toEqual(answer)
    expect(mockedPost).toHaveBeenCalledWith('/chat/messages', { thread_id: 9, message: 'What should I reorder?' })
  })

  it('sendMessage passes a null thread_id when none is supplied', async () => {
    mockedPost.mockResolvedValueOnce({ data: { data: {} as ChatAnswer } as ApiItem<ChatAnswer> } as never)

    await chatApi.sendMessage(null, 'hi')
    expect(mockedPost).toHaveBeenCalledWith('/chat/messages', { thread_id: null, message: 'hi' })
  })
})
