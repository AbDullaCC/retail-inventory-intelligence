import { useEffect, useRef } from 'react'
import type { ReactNode } from 'react'
import type { ChatMessage } from '../../types'
import { MessageBubble } from './MessageBubble'

/**
 * Renders the message list and auto-scrolls to the bottom on new messages.
 * No ScrollArea primitive exists in the design system, so the scroll is driven
 * by the parent container and a sentinel ref.
 */
export function MessageList({
  messages,
  footer,
}: {
  messages: ChatMessage[]
  /** Optional in-flight node (e.g. TypingIndicator) pinned to the bottom. */
  footer?: ReactNode
}) {
  const bottomRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    // jsdom doesn't implement scrollIntoView; guard so tests don't blow up.
    bottomRef.current?.scrollIntoView?.({ behavior: 'smooth', block: 'end' })
  }, [messages, footer])

  return (
    <div className="flex flex-col gap-4 py-4">
      {messages.map((m) => (
        <MessageBubble key={m.id} message={m} />
      ))}
      {footer}
      <div ref={bottomRef} />
    </div>
  )
}
