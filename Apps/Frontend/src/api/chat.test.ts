import { describe, expect, it, vi, beforeEach } from 'vitest'
import type { Mock } from 'vitest'

vi.mock('../lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn() },
}))

import { api } from '../lib/api'
import { chatApi } from './chat'

describe('chatApi', () => {
  beforeEach(() => vi.clearAllMocks())

  it('unwraps the data envelope for threads', async () => {
    ;(api.get as Mock).mockResolvedValue({ data: { data: [{ id: 1 }] } })

    await expect(chatApi.threads()).resolves.toEqual([{ id: 1 }])
    expect(api.get).toHaveBeenCalledWith('/chat/threads')
  })

  it('fetches a single thread by id', async () => {
    ;(api.get as Mock).mockResolvedValue({ data: { data: { id: 7 } } })

    await expect(chatApi.thread(7)).resolves.toEqual({ id: 7 })
    expect(api.get).toHaveBeenCalledWith('/chat/threads/7')
  })

  it('posts a message with a null thread id for new conversations', async () => {
    ;(api.post as Mock).mockResolvedValue({ data: { data: { thread: { id: 1 }, message: { id: 2 } } } })

    await expect(chatApi.send(null, 'hi')).resolves.toEqual({ thread: { id: 1 }, message: { id: 2 } })
    expect(api.post).toHaveBeenCalledWith('/chat/messages', { thread_id: null, message: 'hi' })
  })
})
