import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ChatPanel } from './ChatPanel'
import type { ChatAnswer, ChatThread } from '../../types'

vi.mock('../../api/chat', () => ({
  chatApi: {
    threads: vi.fn(),
    thread: vi.fn(),
    send: vi.fn(),
  },
}))

import { chatApi } from '../../api/chat'

const mocked = vi.mocked(chatApi)

function answer(text: string, threadId = 1): ChatAnswer {
  return {
    thread: {
      id: threadId,
      title: 'Test thread',
      message_count: 2,
      last_message_at: '2026-07-11T10:00:00Z',
      created_at: '2026-07-11T10:00:00Z',
    },
    message: {
      id: 2,
      thread_id: threadId,
      role: 'assistant',
      content: text,
      tool_calls: [{ name: 'get_recommendations', summary: 'get_recommendations: 20 of 55 recommendations' }],
      created_at: '2026-07-11T10:00:01Z',
    },
  }
}

describe('ChatPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mocked.threads.mockResolvedValue([])
  })

  it('shows suggested prompts on the empty state', async () => {
    render(<ChatPanel />)

    expect(await screen.findByText('What should I reorder this week?')).toBeInTheDocument()
    expect(screen.getByText('Ask me about your inventory')).toBeInTheDocument()
  })

  it('sends a message and renders the assistant reply with source chips', async () => {
    mocked.send.mockResolvedValue(answer('Order 120 units of Alarm Clock Red.'))
    const user = userEvent.setup()
    render(<ChatPanel />)

    await user.type(screen.getByLabelText('Message the assistant'), 'what should I reorder?')
    await user.click(screen.getByLabelText('Send message'))

    expect(await screen.findByText('Order 120 units of Alarm Clock Red.')).toBeInTheDocument()
    expect(screen.getByText('what should I reorder?')).toBeInTheDocument()
    expect(screen.getByText('get_recommendations')).toBeInTheDocument()
    expect(mocked.send).toHaveBeenCalledWith(null, 'what should I reorder?')
  })

  it('clicking a suggested prompt sends it', async () => {
    mocked.send.mockResolvedValue(answer('Nothing urgent today.'))
    const user = userEvent.setup()
    render(<ChatPanel />)

    await user.click(await screen.findByText('Any dead stock I should clear out?'))

    expect(await screen.findByText('Nothing urgent today.')).toBeInTheDocument()
    expect(mocked.send).toHaveBeenCalledWith(null, 'Any dead stock I should clear out?')
  })

  it('shows the backend error and withdraws the optimistic bubble on failure', async () => {
    // apiErrorMessage only unwraps axios errors — mimic the axios flag.
    mocked.send.mockRejectedValue({
      isAxiosError: true,
      response: { data: { message: 'Gemini API error (HTTP 400): API key not valid.' } },
    })
    const user = userEvent.setup()
    render(<ChatPanel />)

    await user.type(screen.getByLabelText('Message the assistant'), 'hello')
    await user.click(screen.getByLabelText('Send message'))

    expect(await screen.findByRole('alert')).toHaveTextContent('API key not valid')
    // Optimistic user bubble is withdrawn → back to the empty state.
    await waitFor(() => expect(screen.queryByText('hello')).not.toBeInTheDocument())
  })

  it('lists past conversations and opens one', async () => {
    const threads: ChatThread[] = [
      { id: 5, title: 'Reorder chat', message_count: 4, last_message_at: '2026-07-10T09:00:00Z', created_at: '2026-07-10T09:00:00Z' },
    ]
    mocked.threads.mockResolvedValue(threads)
    mocked.thread.mockResolvedValue({
      ...threads[0],
      messages: [
        { id: 1, thread_id: 5, role: 'user', content: 'old question', tool_calls: null, created_at: '2026-07-10T09:00:00Z' },
        { id: 2, thread_id: 5, role: 'assistant', content: 'old answer', tool_calls: null, created_at: '2026-07-10T09:00:01Z' },
      ],
    })
    const user = userEvent.setup()
    render(<ChatPanel />)

    await user.click(screen.getByText('Past conversations'))
    await user.click(await screen.findByText('Reorder chat'))

    expect(await screen.findByText('old answer')).toBeInTheDocument()
    expect(screen.getByText('old question')).toBeInTheDocument()
    expect(mocked.thread).toHaveBeenCalledWith(5)
  })
})
