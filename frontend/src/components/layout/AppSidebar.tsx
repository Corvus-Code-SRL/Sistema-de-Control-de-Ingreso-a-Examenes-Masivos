import { useMemo } from 'react'
import { LogIn, LogOut } from 'lucide-react'
import { NavLink, useLocation, useNavigate } from 'react-router-dom'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { AREA_LABELS, LOGIN_PATH, useAuth, useCurrentUser } from '@/features/auth'
import { usePendingIncidentsCount } from '@/features/incidents'
import type { NavBadge } from './navigation'
import { findActiveItem, visibleSections } from './navigationSelectors'
import { SidebarSection } from './SidebarSection'
import { useSidebarSections } from './useSidebarSections'

interface AppSidebarProps {
  /** Al navegar en móvil hay que cerrar el panel que contiene el menú. */
  onNavigate?: () => void
}

/**
 * Navegación lateral: en escritorio es fija, en móvil vive dentro de un panel.
 *
 * Las secciones salen de las capacidades del usuario, no de su rol (ver `visibleSections`), y se
 * pueden contraer; cuáles están abiertas se recuerda por usuario.
 *
 * El `<nav>` es el único elemento que se desplaza (la cabecera y la cuenta quedan fijas) y no
 * muestra barra de desplazamiento; sigue respondiendo al tacto, la rueda y el foco por teclado.
 *
 * Va sobre el teal profundo de la marca, así que sus colores salen de los tokens
 * del sidebar y no de los de superficie clara: el gris de texto secundario de la
 * aplicación resultaría ilegible aquí.
 */
export function AppSidebar({ onNavigate }: AppSidebarProps) {
  const { user, area, capabilities } = useCurrentUser()
  const { estado, usuario, cerrarSesion } = useAuth()
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const hasSession = estado === 'autenticado'

  const sections = useMemo(() => visibleSections(capabilities), [capabilities])
  const sectionIds = useMemo(() => sections.map((section) => section.id), [sections])
  const active = useMemo(() => findActiveItem(sections, pathname), [sections, pathname])
  const state = useSidebarSections(usuario?.id_usuario, sectionIds, active?.sectionId)

  // Solo se pide el contador si alguna entrada visible lo muestra.
  const showsPendingIncidents = sections.some((section) =>
    section.items.some((item) => item.badge === 'pendingIncidents')
  )
  const pendingIncidents = usePendingIncidentsCount(showsPendingIncidents)
  const badges: Partial<Record<NavBadge, number>> = { pendingIncidents }

  async function handleLogout() {
    await cerrarSesion()
    onNavigate?.()
    navigate(LOGIN_PATH, { replace: true })
  }

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

      <nav
        aria-label="Navegación principal"
        className="scrollbar-hidden flex-1 overflow-y-auto overscroll-contain px-2 py-3"
      >
        {sections.map((section) => (
          <SidebarSection
            key={section.id}
            section={section}
            open={state.isOpen(section.id)}
            onToggle={() => state.toggle(section.id)}
            activeItemId={active?.sectionId === section.id ? active.itemId : undefined}
            badges={badges}
            onNavigate={onNavigate}
          />
        ))}
      </nav>

      <section aria-label="Cuenta" className="border-t border-sidebar-border px-4 py-3">
        <p className="sciem-overline pb-2 text-sb-group">Cuenta</p>

        <div className="flex items-center gap-2.5">
          <Avatar className="size-8">
            <AvatarFallback className="bg-sidebar-accent text-xs font-semibold text-white">
              {user.iniciales}
            </AvatarFallback>
          </Avatar>
          <div className="min-w-0">
            <p className="truncate text-sm font-medium text-white">{user.nombre}</p>
            <p className="truncate text-xs text-sb-sub">{user.rolEtiqueta ?? AREA_LABELS[area]}</p>
          </div>
        </div>

        {hasSession ? (
          <button
            type="button"
            onClick={handleLogout}
            className="mt-3 flex h-9 w-full items-center gap-2 rounded-md px-2 text-sm font-medium text-sidebar-foreground hover:bg-sb-hover"
          >
            <LogOut className="size-4 shrink-0" aria-hidden="true" />
            Cerrar sesión
          </button>
        ) : (
          <NavLink
            to={LOGIN_PATH}
            onClick={onNavigate}
            className="mt-3 flex h-9 w-full items-center gap-2 rounded-md px-2 text-sm font-medium text-sidebar-foreground hover:bg-sb-hover"
          >
            <LogIn className="size-4 shrink-0" aria-hidden="true" />
            Iniciar sesión
          </NavLink>
        )}
      </section>
    </div>
  )
}
