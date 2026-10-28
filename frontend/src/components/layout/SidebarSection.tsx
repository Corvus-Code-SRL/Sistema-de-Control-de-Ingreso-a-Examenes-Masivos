import { useId } from 'react'
import { ChevronDown } from 'lucide-react'
import { cn } from '@/lib/utils'
import type { NavBadge, NavSection } from './navigation'
import { SidebarItem } from './SidebarItem'

interface SidebarSectionProps {
  section: NavSection
  /** Posición (desde 1) entre las secciones visibles. */
  position: number
  open: boolean
  onToggle: () => void
  activeItemId: string | undefined
  badges: Partial<Record<NavBadge, number>>
  onNavigate?: () => void
}

/**
 * Sección colapsable del menú, con su número, el conteo de entradas y el estado abierto o cerrado
 * expuesto al teclado y a los lectores de pantalla (`aria-expanded` y `aria-controls`).
 */
export function SidebarSection({
  section,
  position,
  open,
  onToggle,
  activeItemId,
  badges,
  onNavigate
}: SidebarSectionProps) {
  // El menú puede estar montado dos veces (escritorio y panel móvil): los ids no pueden repetirse.
  const baseId = useId()
  const panelId = `${baseId}-panel`
  const headingId = `${baseId}-heading`

  return (
    <div className="mb-1">
      <h3 id={headingId} className="m-0">
        <button
          type="button"
          aria-expanded={open}
          aria-controls={panelId}
          onClick={onToggle}
          className={cn(
            'flex w-full items-center gap-2 rounded-md px-2 py-2.5 text-left text-sidebar-foreground',
            'outline-none hover:bg-sb-hover focus-visible:ring-2 focus-visible:ring-sidebar-ring',
            open && 'bg-sb-hover'
          )}
        >
          <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-sb-hover text-sb-sub">
            <section.icon className="size-3.5" aria-hidden="true" />
          </span>
          <span className="sciem-overline flex min-w-0 flex-1 items-baseline gap-1.5">
            <span className="text-sb-group" aria-hidden="true">
              {position}
            </span>
            <span className="min-w-0">{section.label}</span>
          </span>
          <span className="text-xs font-semibold text-sb-sub" aria-hidden="true">
            {section.items.length}
          </span>
          <ChevronDown
            className={cn(
              'size-4 shrink-0 text-sb-sub transition-transform motion-reduce:transition-none',
              open && 'rotate-180'
            )}
            aria-hidden="true"
          />
        </button>
      </h3>

      <ul
        id={panelId}
        aria-labelledby={headingId}
        hidden={!open}
        className="mt-1 ml-5 space-y-0.5 border-l border-sb-divider pl-2"
      >
        {section.items.map((item) => (
          <li key={item.id}>
            <SidebarItem
              item={item}
              active={item.id === activeItemId}
              badgeCount={item.badge ? badges[item.badge] : undefined}
              onNavigate={onNavigate}
            />
          </li>
        ))}
      </ul>
    </div>
  )
}
