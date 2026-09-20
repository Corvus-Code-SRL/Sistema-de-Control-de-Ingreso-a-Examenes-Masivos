import { NavLink } from 'react-router-dom'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { cn } from '@/lib/utils'
import { navigation } from './navigation'

interface AppSidebarProps {
  /** Al navegar en móvil hay que cerrar el panel que contiene el menú. */
  onNavigate?: () => void
}

/** Navegación lateral: en escritorio es fija, en móvil vive dentro de un panel. */
export function AppSidebar({ onNavigate }: AppSidebarProps) {
  return (
    <div className="flex h-full flex-col bg-sidebar text-sidebar-foreground">
      <div className="flex items-center gap-2 border-b border-sidebar-border px-4 py-4">
        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-sm font-semibold text-sidebar-primary-foreground">
          S
        </span>
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold">SCIEM</p>
          <p className="truncate text-xs text-muted-foreground">Control de Ingreso a Exámenes</p>
        </div>
      </div>

      <nav aria-label="Navegación principal" className="flex-1 overflow-y-auto px-2 py-3">
        {navigation.map((group, index) => (
          <div key={group.label ?? index} className="mb-3">
            {group.label && (
              <p className="px-2 py-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {group.label}
              </p>
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
                          'flex items-center gap-2.5 rounded-md px-2 py-2 text-sm transition-colors',
                          isActive
                            ? 'bg-sidebar-accent font-medium text-sidebar-accent-foreground'
                            : 'text-sidebar-foreground hover:bg-sidebar-accent/60'
                        )
                      }
                    >
                      <item.icon className="size-4 shrink-0" aria-hidden="true" />
                      <span className="flex-1 truncate">{item.label}</span>
                      {item.badge !== undefined && (
                        <span className="rounded-full bg-destructive/10 px-1.5 text-xs text-destructive">
                          {item.badge}
                        </span>
                      )}
                    </NavLink>
                  ) : (
                    <span
                      aria-disabled="true"
                      title="Disponible en una próxima entrega"
                      className="flex cursor-not-allowed items-center gap-2.5 rounded-md px-2 py-2 text-sm text-muted-foreground/60"
                    >
                      <item.icon className="size-4 shrink-0" aria-hidden="true" />
                      <span className="flex-1 truncate">{item.label}</span>
                      {item.badge !== undefined && (
                        <span className="rounded-full bg-muted px-1.5 text-xs">{item.badge}</span>
                      )}
                    </span>
                  )}
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>

      <div className="flex items-center gap-2 border-t border-sidebar-border px-4 py-3">
        <Avatar className="size-8">
          <AvatarFallback>PC</AvatarFallback>
        </Avatar>
        <div className="min-w-0">
          <p className="truncate text-sm font-medium">P. Careaga</p>
          <p className="truncate text-xs text-muted-foreground">Docente</p>
        </div>
      </div>
    </div>
  )
}
