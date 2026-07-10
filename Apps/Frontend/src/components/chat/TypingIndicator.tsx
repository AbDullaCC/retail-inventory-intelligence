/**
 * Three-dot "assistant is thinking" indicator shown for the full v1 round-trip
 * (5–30s with tool calls). Streaming deltas replace this in M5.
 */
export function TypingIndicator() {
  return (
    <div className="flex items-center gap-1.5 px-1 py-2" aria-label="Assistant is typing" role="status">
      {[0, 1, 2].map((i) => (
        <span
          key={i}
          className="h-2 w-2 animate-bounce rounded-full bg-brand-400"
          style={{ animationDelay: `${i * 0.15}s` }}
        />
      ))}
    </div>
  )
}
