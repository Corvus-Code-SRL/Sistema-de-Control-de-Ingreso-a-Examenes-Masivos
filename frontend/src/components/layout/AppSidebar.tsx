import { useEffect, useLayoutEffect, useMemo, useRef, type UIEvent } from 'react'
import { LogIn, LogOut, PanelLeftClose } from 'lucide-react'
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
  /** Solo en escritorio: oculta el menú. Sin esta función (panel móvil) no se muestra el botón. */
  onCollapse?: () => void
  /** `id` del contenedor que `onCollapse` oculta, para `aria-controls`. */
  controlsId?: string
  /**
   * Solo en el panel móvil, que se desmonta al cerrarse: recibe el desplazamiento del `<nav>`
   * (como mucho una vez por fotograma) para que quien lo contiene lo recuerde.
   */
  onNavScroll?: (top: number) => void
  /**
   * Solo en el panel móvil: desplazamiento con el que debe nacer el `<nav>`. Su presencia (aunque
   * sea 0) activa la restauración y el ajuste de la entrada activa al montar.
   */
  initialNavScroll?: number
}

/** `true` si el elemento cabe entero dentro del área visible del contenedor. */
function isFullyVisible(container: HTMLElement, element: HTMLElement): boolean {
  const area = container.getBoundingClientRect()
  const box = element.getBoundingClientRect()

  return box.top >= area.top && box.bottom <= area.bottom
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
 * En el panel móvil el menú se vuelve a montar en cada apertura. Para que reabrirlo lo deje donde
 * estaba, `onNavScroll` informa del desplazamiento e `initialNavScroll` lo restaura antes del
 * primer pintado (sin salto visible). Después, si la entrada activa quedó fuera del área visible,
 * se acerca con `block: 'nearest'`; si ya se ve, la posición se conserva exacta. Todo es
 * instantáneo (nunca `behavior: 'smooth'`), así que `prefers-reduced-motion` se respeta.
 *
 * Va sobre el teal profundo de la marca, así que sus colores salen de los tokens
 * del sidebar y no de los de superficie clara: el gris de texto secundario de la
 * aplicación resultaría ilegible aquí.
 */
export function AppSidebar({
  onNavigate,
  onCollapse,
  controlsId,
  onNavScroll,
  initialNavScroll
}: AppSidebarProps) {
  const navRef = useRef<HTMLElement>(null)
  const latestScroll = useRef(0)
  const pendingFrame = useRef<number | null>(null)
  const reportScroll = useRef(onNavScroll)

  useLayoutEffect(() => {
    reportScroll.current = onNavScroll
  })

  // Al montar (una vez): restaura el desplazamiento y acerca la entrada activa si quedó fuera.
  useLayoutEffect(() => {
    const nav = navRef.current
    if (nav === null || initialNavScroll === undefined) return

    nav.scrollTop = initialNavScroll

    const activeLink = nav.querySelector<HTMLElement>('[aria-current="page"]')

    if (activeLink !== null && !isFullyVisible(nav, activeLink)) {
      activeLink.scrollIntoView({ block: 'nearest' })
    }
    // Solo al montar: después el usuario manda sobre el desplazamiento.
  }, [])

  // Al desmontar, un fotograma pendiente no debe perder el último valor: se entrega al instante.
  useEffect(
    () => () => {
      if (pendingFrame.current === null) return

      cancelAnimationFrame(pendingFrame.current)
      pendingFrame.current = null
      reportScroll.current?.(latestScroll.current)
    },
    []
  )

  function handleNavScroll(event: UIEvent<HTMLElement>) {
    if (reportScroll.current === undefined) return

    latestScroll.current = event.currentTarget.scrollTop

    // Un aviso por fotograma como máximo: el desplazamiento dispara decenas de eventos por segundo.
    if (pendingFrame.current !== null) return

    pendingFrame.current = requestAnimationFrame(() => {
      pendingFrame.current = null
      reportScroll.current?.(latestScroll.current)
    })
  }

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
        <div className="min-w-0 flex-1">
          <p className="sciem-wordmark truncate">SCIEM</p>
          <p className="truncate text-xs text-sb-sub">Control de Ingreso a Exámenes</p>
        </div>

        {onCollapse && (
          <button
            type="button"
            onClick={onCollapse}
            aria-label="Ocultar menú"
            aria-expanded={true}
            aria-controls={controlsId}
            className="flex size-8 shrink-0 items-center justify-center rounded-md text-sidebar-foreground outline-none hover:bg-sb-hover focus-visible:ring-2 focus-visible:ring-sidebar-ring"
          >
            <PanelLeftClose className="size-4" aria-hidden="true" />
          </button>
        )}
      </div>

      <nav
        ref={navRef}
        onScroll={handleNavScroll}
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
