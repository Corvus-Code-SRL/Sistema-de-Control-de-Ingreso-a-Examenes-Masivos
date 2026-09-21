import { X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

interface SidebarBrandProps {
  collapsed: boolean
  onClose?: () => void
}

export default function SidebarBrand({ collapsed, onClose }: SidebarBrandProps) {
  return (
    <div
      className={cn(
        'flex h-[76px] shrink-0 items-center gap-3 border-b border-sidebar-border compact:h-16',
        collapsed ? 'justify-center' : 'px-5',
      )}
    >
      <div
        aria-hidden
        className="flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-sidebar-primary font-brand text-[18px] font-bold text-sidebar-primary-foreground"
      >
        S
      </div>
      {!collapsed && (
        <div className="min-w-0 flex-1">
          <p className="font-brand text-[17px] leading-tight font-bold text-sidebar-primary-foreground">
            SCIEM
          </p>
          <p className="truncate text-xs text-sidebar-muted">Control de Ingreso a Exámenes</p>
        </div>
      )}
      {onClose && (
        <Button
          variant="ghost"
          size="icon"
          onClick={onClose}
          aria-label="Cerrar menú"
          className="text-sidebar-muted hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-sidebar-ring"
        >
          <X aria-hidden />
        </Button>
      )}
    </div>
  )
}
