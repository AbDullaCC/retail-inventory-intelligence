/** Three pulsing dots shown while the assistant is thinking. */
export function TypingIndicator() {
  return (
    <div
      className="inline-flex items-center gap-1.5 rounded-2xl rounded-bl-sm border border-cyan-300/30 bg-black/20 px-3.5 py-2.5 shadow-[0_0_20px_-6px_rgb(34_211_238_/0.2)] backdrop-blur-md animate-message-in"
      role="status"
      aria-label="Assistant is thinking"
    >
      {[0, 120, 240].map((delay) => (
        <span
          key={delay}
          className="h-2 w-2 animate-bounce rounded-full bg-cyan-400 shadow-[0_0_10px_2px_rgb(34_211_238_/0.7)]"
          style={{ animationDelay: `${delay}ms` }}
        />
      ))}
    </div>
  )
}
