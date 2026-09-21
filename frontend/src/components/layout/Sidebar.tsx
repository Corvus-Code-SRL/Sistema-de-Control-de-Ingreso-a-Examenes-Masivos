import { Tooltip } from 'radix-ui'
import type { NavItem } from '@/config/navigation'
import type { NavStatus } from '@/config/navStatus'
import SidebarBrand from './SidebarBrand'
import SidebarFooter from './SidebarFooter'
import SidebarNav from './SidebarNav'

interface SidebarProps {
  items: NavItem[]
  status: NavStatus
  collapsed: boolean
  // Toggle de colapso: no se pasa en el drawer móvil.
  onToggle?: () => void
  // Botón de cierre: solo en el drawer móvil.
  onClose?: () => void
}

export default function Sidebar({ items, status, collapsed, onToggle, onClose }: SidebarProps) {
  return (
    <Tooltip.Provider delayDuration={150}>
      <div className="flex h-full flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground">
        <SidebarBrand collapsed={collapsed} onClose={onClose} />
        <SidebarNav items={items} status={status} collapsed={collapsed} />
        <SidebarFooter collapsed={collapsed} onToggle={onToggle} />
      </div>
    </Tooltip.Provider>
  )
}
