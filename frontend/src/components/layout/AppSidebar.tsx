import { NavLink } from 'react-router-dom'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { AREA_LABELS, useCurrentUser } from '@/features/auth'
import { cn } from '@/lib/utils'
import { DevAreaSwitcher } from './DevAreaSwitcher'
import { navigationByArea } from './navigation'

interface AppSidebarProps {
  /** Al navegar en móvil hay que cerrar el panel que contiene el menú. */
  onNavigate?: () => void
}

/**
 * Navegación lateral: en escritorio es fija, en móvil vive dentro de un panel.
 *
 * Va sobre el teal profundo de la marca, así que sus colores salen de los tokens
 * del sidebar y no de los de superficie clara: el gris de texto secundario de la
 * aplicación resultaría ilegible aquí.
 */
export function AppSidebar({ onNavigate }: AppSidebarProps) {
  const { user, area } = useCurrentUser()
  const navigation = navigationByArea[area]

  return (
    <div className="flex h-full flex-col bg-sidebar text-sidebar-foreground">
      <div className="flex items-center gap-2.5 border-b border-sidebar-border px-4 py-4">
        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-sm font-bold text-sidebar-primary-foreground">
          S
        </span>
        <div className="min-w-0">
          <p className="sciem-wordmark truncate">SCIEM</p>
          <p className="truncate text-xs text-sb-sub">Control de Ingreso a Exámenes</p>
        </div>
      </div>

      <nav aria-label="Navegación principal" className="flex-1 overflow-y-auto px-2 py-3">
        {navigation.map((group, index) => (
          <div key={group.label ?? index} className="mb-3">
            {group.label && (
              <p className="sciem-overline px-2 pt-3 pb-1.5 text-sb-group">{group.label}</p>
            )}

            <ul className="space-y-0.5">
              {group.items.map((item) => (
                <li key={item.label}>
                  {item.to ? (
                    <NavLink
                      to={item.to}
                      onClick={onNavigate}
                      className={({ isActive }) =>
                        cn(
                          'flex h-10 items-center gap-3 rounded-md px-3 text-sm transition-colors',
                          isActive
                            ? 'bg-brand font-semibold text-white'
                            : 'font-medium text-sidebar-foreground hover:bg-sb-hover'
                        )
                      }
                    >
                      <item.icon className="size-4 shrink-0" aria-hidden="true" />
                      <span className="flex-1 truncate">{item.label}</span>
                      {item.badge !== undefined && (
                        <span className="rounded-full bg-accent-brand px-1.5 text-xs font-semibold text-brand-deep">
                          {item.badge}
                        </span>
                      )}
                    </NavLink>
                  ) : (
                    <span
                      aria-disabled="true"
                      title="Disponible en una próxima entrega"
                      className="flex h-10 cursor-not-allowed items-center gap-3 rounded-md px-3 text-sm font-medium text-sb-group/70"
                    >
                      <item.icon className="size-4 shrink-0" aria-hidden="true" />
                      <span className="flex-1 truncate">{item.label}</span>
                      {item.badge !== undefined && (
                        <span className="rounded-full bg-sb-hover px-1.5 text-xs text-sb-sub">
                          {item.badge}
                        </span>
                      )}
                    </span>
                  )}
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>

      <div className="flex items-center gap-2.5 border-t border-sidebar-border px-4 py-3">
        <Avatar className="size-8">
          <AvatarFallback className="bg-sidebar-accent text-xs font-semibold text-white">
            {user.iniciales}
          </AvatarFallback>
        </Avatar>
        <div className="min-w-0">
          <p className="truncate text-sm font-medium text-white">{user.nombre}</p>
          <p className="truncate text-xs text-sb-sub">{AREA_LABELS[area]}</p>
        </div>
      </div>

      <DevAreaSwitcher />
    </div>
  )
}
