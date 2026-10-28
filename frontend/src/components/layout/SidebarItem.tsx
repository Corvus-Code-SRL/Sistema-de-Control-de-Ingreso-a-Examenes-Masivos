import { Link } from 'react-router-dom'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'
import { DISABLED_REASON, type NavItem } from './navigation'
import { isConnected } from './navigationSelectors'

interface SidebarItemProps {
  item: NavItem
  active: boolean
  /** Valor del contador de la entrada, si tiene uno (ver `NavItem.badge`). */
  badgeCount?: number
  /** Al navegar en móvil hay que cerrar el panel que contiene el menú. */
  onNavigate?: () => void
}

const ROW_CLASSES =
  'flex min-h-9 w-full items-center gap-2.5 rounded-md px-2.5 py-1.5 text-left text-sm leading-snug ' +
  'outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring'

/**
 * Una entrada del menú: enlace si tiene destino y, si no, un botón deshabilitado que avisa que
 * aún no está disponible. El deshabilitado sigue siendo enfocable para que el teclado también
 * llegue al aviso, pero no navega.
 */
export function SidebarItem({ item, active, badgeCount, onNavigate }: SidebarItemProps) {
  if (!isConnected(item)) {
    return (
      <Tooltip>
        <TooltipTrigger asChild>
          <button
            type="button"
            aria-disabled="true"
            className={cn(ROW_CLASSES, 'cursor-not-allowed text-sb-group/70')}
          >
            <item.icon className="size-4 shrink-0" aria-hidden="true" />
            <span className="flex-1">{item.label}</span>
          </button>
        </TooltipTrigger>
        <TooltipContent side="right">{DISABLED_REASON}</TooltipContent>
      </Tooltip>
    )
  }

  return (
    <Link
      to={item.to}
      onClick={onNavigate}
      aria-current={active ? 'page' : undefined}
      className={cn(
        ROW_CLASSES,
        'transition-colors',
        active
          ? 'bg-brand font-semibold text-white'
          : 'font-medium text-sidebar-foreground hover:bg-sb-hover'
      )}
    >
      <item.icon className="size-4 shrink-0" aria-hidden="true" />
      <span className="flex-1">{item.label}</span>
      {badgeCount !== undefined && badgeCount > 0 && (
        <span className="rounded-full bg-warn px-1.5 text-xs font-semibold text-warn-fg">
          {badgeCount}
        </span>
      )}
    </Link>
  )
}
