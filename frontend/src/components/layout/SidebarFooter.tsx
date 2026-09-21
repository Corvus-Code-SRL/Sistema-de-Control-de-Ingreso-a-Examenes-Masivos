import { ChevronsLeft, ChevronsRight } from 'lucide-react'
import { cn } from '@/lib/utils'
import AccountMenu from './AccountMenu'
import SidebarTooltip from './SidebarTooltip'

interface SidebarFooterProps {
  collapsed: boolean
  onToggle?: () => void
}

export default function SidebarFooter({ collapsed, onToggle }: SidebarFooterProps) {
  const ToggleIcon = collapsed ? ChevronsRight : ChevronsLeft

  return (
    <div className="flex shrink-0 flex-col gap-1 border-t border-sidebar-border px-3 pt-1.5 pb-2 compact:pt-1 compact:pb-1.5">
      {onToggle && (
        <SidebarTooltip label="Expandir menú" enabled={collapsed}>
          <button
            type="button"
            onClick={onToggle}
            aria-expanded={!collapsed}
            className={cn(
              'flex h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium text-sidebar-muted outline-none compact:h-9 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring',
              collapsed && 'justify-center px-0',
            )}
          >
            <ToggleIcon className="size-5 shrink-0" aria-hidden />
            <span className={cn('whitespace-nowrap', collapsed && 'sr-only')}>
              {collapsed ? 'Expandir menú' : 'Contraer menú'}
            </span>
          </button>
        </SidebarTooltip>
      )}
      <AccountMenu collapsed={collapsed} />
    </div>
  )
}
