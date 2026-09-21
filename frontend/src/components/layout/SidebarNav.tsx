import { groupNav, type NavItem } from '@/config/navigation'
import type { NavStatus } from '@/config/navStatus'
import SidebarNavItem from './SidebarNavItem'

interface SidebarNavProps {
  items: NavItem[]
  status: NavStatus
  collapsed: boolean
}

export default function SidebarNav({ items, status, collapsed }: SidebarNavProps) {
  return (
    <nav
      aria-label="Navegación principal"
      className="scrollbar-sidebar flex flex-1 flex-col gap-0.5 overflow-y-auto overscroll-contain px-3 pt-3 pb-2 compact:gap-px compact:pt-2 compact:pb-1"
    >
      {groupNav(items).map((group) => (
        <div key={group.label ?? 'sin-grupo'} className="flex flex-col gap-0.5 compact:gap-px">
          {group.label &&
            (collapsed ? (
              <div
                role="separator"
                className="mx-auto mt-3 mb-2 h-px w-6 bg-sidebar-divider compact:mt-2 compact:mb-1.5"
              />
            ) : (
              <p className="truncate px-3 pt-3 pb-2 text-[11px] font-semibold tracking-[0.08em] text-sidebar-label uppercase compact:pt-2 compact:pb-1.5">
                {group.label}
              </p>
            ))}
          {group.items.map((item) => (
            <SidebarNavItem key={item.key} item={item} status={status} collapsed={collapsed} />
          ))}
        </div>
      ))}
    </nav>
  )
}
