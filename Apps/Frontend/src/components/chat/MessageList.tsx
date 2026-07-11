import { useEffect, useRef } from 'react'
import { MessageBubble } from './MessageBubble'
import { TypingIndicator } from './TypingIndicator'
import type { ChatMessage } from '../../types'

/** The scrolling conversation; keeps itself pinned to the newest message. */
export function MessageList({ messages, thinking }: { messages: ChatMessage[]; thinking: boolean }) {
  const endRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    // Optional-call: jsdom (Vitest) doesn't implement scrollIntoView.
    endRef.current?.scrollIntoView?.({ block: 'end' })
  }, [messages.length, thinking])

  return (
    <div className="flex-1 space-y-3 overflow-y-auto px-4 py-4">
      {messages.map((message) => (
        <MessageBubble key={message.id} message={message} />
      ))}
      {thinking && <TypingIndicator />}
      <div ref={endRef} />
    </div>
  )
}
