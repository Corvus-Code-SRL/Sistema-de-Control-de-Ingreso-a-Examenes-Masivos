import { useId, useRef, useState, type CSSProperties, type ReactNode, type Ref } from 'react'
import { Menu, PanelLeftOpen } from 'lucide-react'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button, buttonVariants } from '@/components/ui/button'
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import { useAuth, useCurrentUser } from '@/features/auth'
import { cn } from '@/lib/utils'
import { AppSidebar } from './AppSidebar'
import { SidebarResizeHandle } from './SidebarResizeHandle'
import { useSidebarLayout } from './useSidebarLayout'

interface AppFrameProps {
  /** `layout`: marco persistente de `AppLayout`. `standalone`: marco propio de una pantalla aislada. */
  mode: 'layout' | 'standalone'
  /** Contenido de la barra superior propio de la pantalla (migas, título móvil y período). */
  header: ReactNode
  mainRef?: Ref<HTMLElement>
  children: ReactNode
}

/**
 * Marco de la aplicación: navegación lateral (fija y redimensionable en escritorio, desplegable en
 * móvil), barra superior y zona de contenido.
 *
 * En escritorio el menú se puede ocultar. Se oculta sin desmontarlo (ancho cero y `invisible`), de
 * modo que conserva su desplazamiento, sus secciones abiertas y sus consultas.
 */
export function AppFrame({ mode, header, mainRef, children }: AppFrameProps) {
  const { user } = useCurrentUser()
  const { usuario } = useAuth()
  const sidebarLayout = useSidebarLayout(usuario?.id_usuario)
  const { collapsed, width } = sidebarLayout
  const [menuOpen, setMenuOpen] = useState(false)
  const asideId = useId()
  const asideRef = useRef<HTMLElement>(null)

  return (
    <div data-shell={mode} className="flex min-h-screen bg-background">
      <aside
        ref={asideRef}
        id={asideId}
        data-collapsed={collapsed}
        style={{ '--sidebar-width': `${width}px` } as CSSProperties}
        className={cn(
          'relative hidden shrink-0 transition-[width] duration-200 ease-out lg:block print:hidden',
          'motion-reduce:transition-none data-[resizing=true]:transition-none',
          collapsed ? 'w-0' : 'w-[var(--sidebar-width)]'
        )}
      >
        <div className="sticky top-0 h-screen overflow-hidden">
          {/*
            Ancho fijo dentro de un contenedor que se encoge: el texto no se reacomoda mientras se
            anima. `invisible` llega al final de la transición y saca el menú del foco y del árbol
            de accesibilidad.
          */}
          <div
            className={cn(
              'h-full w-[var(--sidebar-width)] border-r border-sidebar-border',
              'transition-[visibility] duration-200 motion-reduce:transition-none',
              collapsed && 'invisible'
            )}
          >
            <AppSidebar onCollapse={sidebarLayout.toggle} controlsId={asideId} />
          </div>

          {!collapsed && (
            <SidebarResizeHandle
              width={width}
              targetRef={asideRef}
              onResize={sidebarLayout.setWidth}
              onReset={sidebarLayout.resetWidth}
            />
          )}
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-20 flex items-center gap-2 border-b border-border bg-background/95 px-4 py-2.5 backdrop-blur print:hidden">
          <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
            {/*
              Sin `asChild`: las primitivas de shadcn no usan forwardRef y en React 18
              el ref de Radix no llegaría al botón. El estilo se toma de buttonVariants.
            */}
            <SheetTrigger
              className={cn(buttonVariants({ variant: 'ghost', size: 'icon' }), 'lg:hidden')}
              aria-label="Abrir menú"
            >
              <Menu className="size-5" />
            </SheetTrigger>
            <SheetContent side="left" className="w-72 p-0">
              <SheetTitle className="sr-only">Navegación principal</SheetTitle>
              <AppSidebar onNavigate={() => setMenuOpen(false)} />
            </SheetContent>
          </Sheet>

          {collapsed && (
            <Button
              type="button"
              variant="ghost"
              size="icon"
              aria-label="Mostrar menú"
              aria-expanded={false}
              aria-controls={asideId}
              onClick={sidebarLayout.toggle}
              className="hidden lg:inline-flex"
            >
              <PanelLeftOpen className="size-5" aria-hidden="true" />
            </Button>
          )}

          {header}

          <Avatar className="ml-auto size-8 shrink-0 lg:hidden">
            <AvatarFallback className="text-xs">{user.iniciales}</AvatarFallback>
          </Avatar>
        </header>

        <main ref={mainRef} tabIndex={-1} className="flex-1 px-4 py-5 outline-none sm:px-6 sm:py-6">
          {children}
        </main>
      </div>
    </div>
  )
}
