import { ChevronsUpDown, LogOut, MonitorSmartphone, User, type LucideIcon } from 'lucide-react'
import { Popover } from 'radix-ui'
import { cn } from '@/lib/utils'
import { currentUser } from '@/config/currentUser'
import SidebarTooltip from './SidebarTooltip'

const itemClass =
  'flex h-10 w-full items-center gap-3 rounded-lg px-3 text-sm outline-none hover:bg-sidebar-popover-border/60 focus-visible:ring-2 focus-visible:ring-sidebar-ring'

function AccountItem({
  icon: Icon,
  label,
  className,
}: {
  icon: LucideIcon
  label: string
  className?: string
}) {
  // TODO: conectar las acciones de la cuenta cuando exista el módulo de auth.
  return (
    <Popover.Close asChild>
      <button type="button" className={cn(itemClass, className)}>
        <Icon className="size-[18px] shrink-0" aria-hidden />
        {label}
      </button>
    </Popover.Close>
  )
}

export default function AccountMenu({ collapsed }: { collapsed: boolean }) {
  return (
    <Popover.Root>
      <SidebarTooltip label={currentUser.name} enabled={collapsed}>
        <Popover.Trigger asChild>
          <button
            type="button"
            className={cn(
              'flex items-center gap-3 rounded-lg px-2 py-2 text-left outline-none compact:py-1.5 hover:bg-sidebar-accent focus-visible:ring-2 focus-visible:ring-sidebar-ring',
              collapsed ? 'justify-center' : 'w-full',
            )}
          >
            <span
              aria-hidden
              className="flex size-9 shrink-0 items-center justify-center rounded-full bg-sidebar-foreground text-[13px] font-bold text-sidebar"
            >
              {currentUser.initials}
            </span>
            {collapsed ? (
              <span className="sr-only">{currentUser.name}</span>
            ) : (
              <>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm font-semibold text-sidebar-primary-foreground">
                    {currentUser.name}
                  </span>
                  <span className="block truncate text-xs text-sidebar-muted">
                    {currentUser.roleLabel}
                  </span>
                </span>
                <ChevronsUpDown className="size-4 shrink-0 text-sidebar-muted" aria-hidden />
              </>
            )}
          </button>
        </Popover.Trigger>
      </SidebarTooltip>
      <Popover.Portal>
        <Popover.Content
          side={collapsed ? 'right' : 'top'}
          align={collapsed ? 'end' : 'start'}
          sideOffset={8}
          className="z-[60] w-[232px] rounded-[12px] border border-sidebar-popover-border bg-sidebar-popover p-1.5 text-sidebar-foreground shadow-2xl shadow-black/50 outline-none"
        >
          <p className="px-3 pt-2 pb-2 text-[11px] font-semibold tracking-[0.08em] text-sidebar-label uppercase">
            Cuenta
          </p>
          <AccountItem icon={User} label="Mi usuario" />
          <AccountItem icon={MonitorSmartphone} label="Sesiones activas" />
          <div role="separator" className="my-1.5 h-px bg-sidebar-popover-border" />
          <AccountItem icon={LogOut} label="Cerrar sesión" className="text-sidebar-danger" />
        </Popover.Content>
      </Popover.Portal>
    </Popover.Root>
  )
}
