import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import axios from 'axios'
import { ChatPanel } from './ChatPanel'
import type { ChatAnswer } from '../../types'

// Mock the chat API so the panel never touches the network.
vi.mock('../../api/chat', () => ({
  chatApi: {
    sendMessage: vi.fn(),
    getThreads: vi.fn(),
    getThread: vi.fn(),
  },
}))

import { chatApi } from '../../api/chat'
import type { ChatThread } from '../../types'

const mockedSend = vi.mocked(chatApi.sendMessage)
const mockedGetThreads = vi.mocked(chatApi.getThreads)
const mockedGetThread = vi.mocked(chatApi.getThread)

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

function answer(overrides: Partial<ChatAnswer> = {}): ChatAnswer {
  return {
    thread: {
      id: 1,
      user_id: 7,
      title: 'What should I reorder?',
      message_count: 2,
      last_message_at: '2026-07-10T10:01:00Z',
      created_at: '2026-07-10T10:00:00Z',
      messages: null,
    },
    message: {
      id: 2,
      thread_id: 1,
      role: 'assistant',
      content: 'You should reorder Cola.',
      tool_calls: [{ name: 'get_reorder_recommendations', args: {}, result_summary: '2 items to reorder' }],
      created_at: '2026-07-10T10:01:00Z',
    },
    ...overrides,
  }
}

describe('ChatPanel', () => {
  beforeEach(() => {
    mockedSend.mockReset()
    mockedGetThreads.mockReset()
    mockedGetThread.mockReset()
    // The panel loads threads on mount; default to an empty list.
    mockedGetThreads.mockResolvedValue([])
  })

  it('renders the empty state with suggested prompts', async () => {
    render(<ChatPanel />)
    expect(screen.getByText('Ask Shelfwise anything')).toBeInTheDocument()
    expect(screen.getByText('What should I reorder this week?')).toBeInTheDocument()
  })

  it('sends a message from the composer and renders the assistant reply', async () => {
    mockedSend.mockResolvedValueOnce(answer())
    render(<ChatPanel />)

    const input = screen.getByLabelText('Message')
    fireEvent.change(input, { target: { value: 'What should I reorder?' } })
    fireEvent.keyDown(input, { key: 'Enter' })

    // Optimistic user bubble appears immediately.
    await waitFor(() => expect(screen.getByText('What should I reorder?')).toBeInTheDocument())

    // Assistant reply + cited tool card appear after the round-trip.
    await waitFor(() => expect(screen.getByText('You should reorder Cola.')).toBeInTheDocument())
    expect(screen.getByText('Get Reorder Recommendations')).toBeInTheDocument()
    expect(mockedSend).toHaveBeenCalledWith(null, 'What should I reorder?')
  })

  it('shows a typing indicator while awaiting a response', async () => {
    let resolveSend: (v: ChatAnswer) => void = () => {}
    mockedSend.mockImplementationOnce(
      () => new Promise((resolve) => { resolveSend = resolve }),
    )

    render(<ChatPanel />)
    fireEvent.change(screen.getByLabelText('Message'), { target: { value: 'hi' } })
    fireEvent.keyDown(screen.getByLabelText('Message'), { key: 'Enter' })

    await waitFor(() => expect(screen.getByRole('status')).toBeInTheDocument())

    // Resolve and confirm the indicator disappears.
    resolveSend(answer())
    await waitFor(() => expect(screen.queryByRole('status')).not.toBeInTheDocument())
  })

  it('shows the real Gemini error message on a 503', async () => {
    // The backend surfaces the actual Gemini cause in `message`; the UI must
    // show that verbatim instead of a generic "unavailable" string. A real
    // AxiosError is used so apiErrorMessage recognises it as an axios error.
    const err = new axios.AxiosError(
      'Request failed with status code 503',
      'ERR_BAD_RESPONSE',
      undefined,
      undefined,
      {
        status: 503,
        data: { message: 'Gemini API error (HTTP 503): This model is currently experiencing high demand.' },
      } as never,
    )
    mockedSend.mockRejectedValueOnce(err)

    render(<ChatPanel />)
    fireEvent.change(screen.getByLabelText('Message'), { target: { value: 'hi' } })
    fireEvent.keyDown(screen.getByLabelText('Message'), { key: 'Enter' })

    await waitFor(() => expect(screen.getByText(/high demand/)).toBeInTheDocument())
  })

  it('lists past threads and loads messages when one is opened', async () => {
    const pastThreads = [
      thread({ id: 11, title: 'Reorder question', message_count: 4, last_message_at: '2026-07-09T12:00:00Z' }),
      thread({ id: 12, title: 'Dead stock', message_count: 2, last_message_at: '2026-07-08T09:00:00Z' }),
    ]
    mockedGetThreads.mockResolvedValueOnce(pastThreads)

    const fetched: ChatThread = {
      id: 11,
      user_id: 7,
      title: 'Reorder question',
      message_count: 4,
      last_message_at: '2026-07-09T12:00:00Z',
      created_at: '2026-07-09T11:00:00Z',
      messages: [
        { id: 1, thread_id: 11, role: 'user', content: 'old question', tool_calls: null, created_at: '2026-07-09T11:00:00Z' },
        { id: 2, thread_id: 11, role: 'assistant', content: 'old answer', tool_calls: null, created_at: '2026-07-09T11:01:00Z' },
      ],
    }
    mockedGetThread.mockResolvedValueOnce(fetched)

    render(<ChatPanel />)

    // Open the history section and see both threads.
    await waitFor(() => expect(mockedGetThreads).toHaveBeenCalled())
    fireEvent.click(screen.getByRole('button', { name: /History/i }))

    await waitFor(() => expect(screen.getByText('Reorder question')).toBeInTheDocument())
    expect(screen.getByText('Dead stock')).toBeInTheDocument()

    // Clicking a thread fetches and shows its persisted messages.
    fireEvent.click(screen.getByText('Reorder question'))
    await waitFor(() => expect(mockedGetThread).toHaveBeenCalledWith(11))
    await waitFor(() => expect(screen.getByText('old answer')).toBeInTheDocument())
    expect(screen.queryByText('Ask Shelfwise anything')).not.toBeInTheDocument()
  })
})
