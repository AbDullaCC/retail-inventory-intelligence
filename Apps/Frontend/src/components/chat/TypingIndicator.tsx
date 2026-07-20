import { AssistantGlyph } from './AssistantGlyph'

/** The assistant's "reading the ledger" state — matches the reply bubbles. */
export function TypingIndicator() {
  return (
    <div
      className="flex items-start gap-2 motion-safe:animate-msg-in"
      role="status"
      aria-label="Assistant is thinking"
    >
      <AssistantGlyph className="mt-0.5 h-6 w-6" iconClassName="h-3 w-3" />
      <div className="inline-flex items-center gap-2 rounded-2xl rounded-tl-md bg-white px-3.5 py-2.5 shadow-card ring-1 ring-slate-200/70">
        <span className="flex items-center gap-1">
          {[0, 200, 400].map((delay) => (
            <span
              key={delay}
              className="h-1.5 w-1.5 rounded-full bg-brand-500 motion-safe:animate-chat-dot"
              style={{ animationDelay: `${delay}ms` }}
            />
          ))}
        </span>
        <span className="text-xs text-slate-400">Checking your data…</span>
      </div>
    </div>
  )
}
