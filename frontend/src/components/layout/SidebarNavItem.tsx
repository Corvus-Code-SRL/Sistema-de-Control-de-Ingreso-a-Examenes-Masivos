import { NavLink } from 'react-router'
import { cn } from '@/lib/utils'
import type { NavItem } from '@/config/navigation'
import type { NavStatus } from '@/config/navStatus'
import SidebarBadge from './SidebarBadge'
import SidebarTooltip from './SidebarTooltip'

interface SidebarNavItemProps {
  item: NavItem
  status: NavStatus
  collapsed: boolean
}

export default function SidebarNavItem({ item, status, collapsed }: SidebarNavItemProps) {
  const Icon = item.icon

  return (
    <SidebarTooltip label={item.label} enabled={collapsed}>
      <NavLink
        to={item.path}
        end={item.path === '/'}
        // className siempre como string, nunca como función: en modo colapsado
        // SidebarTooltip envuelve este NavLink con Tooltip.Trigger asChild, y el
        // Slot de Radix hace join(' ') sobre la prop, lo que convertiría la
        // función en texto y perdería las clases. El estado activo se estiliza
        // con el aria-current que NavLink ya pone por su cuenta.
        className={cn(
          'relative flex h-10 items-center gap-3 overflow-hidden rounded-lg px-3 text-sm font-medium text-sidebar-foreground outline-none compact:h-9 focus-visible:ring-2 focus-visible:ring-sidebar-ring',
          'not-aria-[current=page]:hover:bg-sidebar-accent not-aria-[current=page]:hover:text-sidebar-accent-foreground',
          'aria-[current=page]:bg-sidebar-primary aria-[current=page]:font-semibold aria-[current=page]:text-sidebar-primary-foreground',
          collapsed && 'justify-center px-0',
        )}
      >
        {({ isActive }) => (
          <>
            {isActive && (
              // Dentro del ítem (left-0) para que el nav scrolleable no la recorte.
              <span
                aria-hidden
                className="absolute top-1/2 left-0 h-6 w-[3px] -translate-y-1/2 rounded-r-[3px] bg-sidebar-highlight"
              />
            )}
            <Icon className="size-5 shrink-0" aria-hidden />
            <span className={cn('truncate', collapsed && 'sr-only')}>{item.label}</span>
            {item.badge && <SidebarBadge badge={item.badge} status={status} collapsed={collapsed} />}
          </>
        )}
      </NavLink>
    </SidebarTooltip>
  )
}
