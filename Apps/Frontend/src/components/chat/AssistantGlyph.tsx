import { Sparkles } from 'lucide-react'
import { cn } from '../ui'

/**
 * The assistant's visual identity: a Harbor-teal gradient tile. One component
 * so the launcher, drawer header, message avatars and empty-state hero all
 * read as the same "person". Size/radius come from the caller.
 */
export function AssistantGlyph({
  className,
  iconClassName,
}: {
  className?: string
  iconClassName?: string
}) {
  return (
    <span
      className={cn(
        'flex shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 text-white',
        className,
      )}
      aria-hidden="true"
    >
      <Sparkles className={cn('h-3.5 w-3.5', iconClassName)} />
    </span>
  )
}
