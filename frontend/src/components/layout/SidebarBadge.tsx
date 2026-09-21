import { cn } from '@/lib/utils'
import type { NavBadge } from '@/config/navigation'
import type { NavStatus } from '@/config/navStatus'

interface SidebarBadgeProps {
  badge: NavBadge
  status: NavStatus
  collapsed: boolean
}

export default function SidebarBadge({ badge, status, collapsed }: SidebarBadgeProps) {
  if (badge.type === 'count') {
    const count = status[badge.source]
    if (count <= 0) return null

    return (
      <span
        className={cn(
          'inline-flex items-center justify-center bg-sidebar-highlight font-bold text-sidebar',
          collapsed
            ? 'absolute top-0.5 right-0.5 h-4 min-w-4 rounded-lg px-1 text-[10px]'
            : 'ml-auto h-5 min-w-5 rounded-[10px] px-1.5 text-[11px]',
        )}
      >
        {count}
      </span>
    )
  }

  if (!status[badge.source]) return null
  const isOpen = badge.tone === 'open'
  const dot = (
    <span
      aria-hidden
      className={cn(
        'size-2 shrink-0 rounded-full',
        isOpen ? 'bg-sidebar-highlight ring-[3px] ring-sidebar-highlight/25' : 'bg-sidebar-live-dot',
        collapsed && 'absolute top-1.5 right-1.5',
      )}
    />
  )

  if (collapsed) {
    return (
      <>
        {dot}
        <span className="sr-only">{badge.label}</span>
      </>
    )
  }

  return (
    <span
      className={cn(
        'ml-auto inline-flex items-center gap-1.5 text-[11px] font-semibold',
        isOpen ? 'text-sidebar-highlight' : 'text-sidebar-live-text',
      )}
    >
      {dot}
      {badge.label}
    </span>
  )
}
