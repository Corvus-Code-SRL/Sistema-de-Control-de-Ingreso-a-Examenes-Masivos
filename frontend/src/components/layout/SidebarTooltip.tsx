import type { ReactNode } from 'react'
import { Tooltip } from 'radix-ui'

interface SidebarTooltipProps {
  label: string
  enabled: boolean
  children: ReactNode
}

// Muestra la etiqueta como tooltip solo cuando el sidebar está colapsado.
//
// El hijo debe traer `className` como string: al estar habilitado se envuelve en
// Tooltip.Trigger asChild, y el Slot de Radix fusiona esa prop con join(' '). Un
// className como función (ej. el de NavLink) se convertiría en texto y el hijo
// se quedaría sin estilos.
export default function SidebarTooltip({ label, enabled, children }: SidebarTooltipProps) {
  if (!enabled) return <>{children}</>

  return (
    <Tooltip.Root>
      <Tooltip.Trigger asChild>{children}</Tooltip.Trigger>
      <Tooltip.Portal>
        <Tooltip.Content
          side="right"
          sideOffset={10}
          className="z-[70] rounded-lg border border-sidebar-popover-border bg-sidebar-popover px-3 py-1.5 text-sm font-medium text-sidebar-foreground shadow-lg"
        >
          {label}
        </Tooltip.Content>
      </Tooltip.Portal>
    </Tooltip.Root>
  )
}
