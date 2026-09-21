import { useEffect, useState } from 'react'
import { Outlet, useLocation } from 'react-router'
import { Menu } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useSidebarState } from '@/hooks/useSidebarState'
import { currentUser } from '@/config/currentUser'
import { navByRole } from '@/config/navigation'
import { defaultNavStatus } from '@/config/navStatus'
import { cn } from '@/lib/utils'
import MobileDrawer from './MobileDrawer'
import Sidebar from './Sidebar'

export default function AppShell() {
  const { pathname } = useLocation()
  const { breakpoint, collapsed, toggle } = useSidebarState()
  const [drawerOpen, setDrawerOpen] = useState(false)
  const isMobile = breakpoint === 'mobile'

  const items = navByRole[currentUser.role]
  // TODO: reemplazar por los datos reales (incidencias y fase del examen) cuando exista el backend.
  const status = defaultNavStatus

  // El drawer se cierra al navegar y al salir de la vista móvil.
  useEffect(() => {
    setDrawerOpen(false)
  }, [pathname, isMobile])

  return (
    <div className="flex h-dvh bg-background">
      {isMobile ? (
        <MobileDrawer open={drawerOpen} onOpenChange={setDrawerOpen} items={items} status={status} />
      ) : (
        <aside
          className={cn(
            'h-full shrink-0 overflow-hidden bg-sidebar transition-[width] duration-200',
            collapsed ? 'w-[72px]' : 'w-[272px]',
          )}
        >
          {/* Ancho fijo y sin transición: el sidebar se maqueta contra su ancho
              final desde el primer frame y el aside lo va destapando, así el
              texto no se reacomoda mientras dura la animación. */}
          <div className={cn('h-full', collapsed ? 'w-[72px]' : 'w-[272px]')}>
            <Sidebar items={items} status={status} collapsed={collapsed} onToggle={toggle} />
          </div>
        </aside>
      )}

      <div className="flex min-w-0 flex-1 flex-col">
        {isMobile && (
          <header className="flex h-14 shrink-0 items-center gap-3 border-b px-4">
            <Button
              variant="ghost"
              size="icon"
              onClick={() => setDrawerOpen(true)}
              aria-label="Abrir menú"
              aria-expanded={drawerOpen}
            >
              <Menu aria-hidden />
            </Button>
            <span className="font-brand text-[17px] font-bold text-sidebar">SCIEM</span>
          </header>
        )}
        <main className="flex-1 overflow-y-auto p-4">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
