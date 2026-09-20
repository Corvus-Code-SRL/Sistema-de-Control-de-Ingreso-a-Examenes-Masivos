import { Fragment, useState, type ReactNode } from 'react'
import { Menu } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { buttonVariants } from '@/components/ui/button'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import { cn } from '@/lib/utils'
import { AppSidebar } from './AppSidebar'

export interface Crumb {
  label: string
  to?: string
}

interface AppShellProps {
  /** Título corto para la barra superior móvil, donde no cabe la miga completa. */
  mobileTitle: string
  mobileSubtitle?: string
  breadcrumbs: Crumb[]
  /** Periodo académico vigente, presente en todas las pantallas del diseño. */
  period?: string
  children: ReactNode
}

/**
 * Marco de la aplicación: navegación fija en escritorio y desplegable en móvil.
 *
 * Es el mismo componente en los dos tamaños; lo que cambia es dónde se monta la
 * navegación, no qué contiene.
 */
export function AppShell({
  mobileTitle,
  mobileSubtitle,
  breadcrumbs,
  period,
  children,
}: AppShellProps) {
  const [menuOpen, setMenuOpen] = useState(false)

  return (
    <div className="flex min-h-screen bg-background">
      <aside className="hidden w-68 shrink-0 border-r border-sidebar-border lg:block">
        <div className="sticky top-0 h-screen">
          <AppSidebar />
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-20 flex items-center gap-2 border-b border-border bg-background/95 px-4 py-2.5 backdrop-blur">
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

          <div className="min-w-0 flex-1">
            <Breadcrumb className="hidden lg:block">
              <BreadcrumbList>
                {breadcrumbs.map((crumb, index) => (
                  // El separador es hermano del item, no hijo: ambos son <li>.
                  <Fragment key={`${crumb.label}-${index}`}>
                    <BreadcrumbItem>
                      {crumb.to && index < breadcrumbs.length - 1 ? (
                        <Link to={crumb.to} className="hover:text-foreground">
                          {crumb.label}
                        </Link>
                      ) : (
                        <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
                      )}
                    </BreadcrumbItem>

                    {index < breadcrumbs.length - 1 && <BreadcrumbSeparator />}
                  </Fragment>
                ))}
              </BreadcrumbList>
            </Breadcrumb>

            <div className="lg:hidden">
              <p className="truncate text-sm font-medium">{mobileTitle}</p>
              {mobileSubtitle && (
                <p className="truncate text-xs text-muted-foreground">{mobileSubtitle}</p>
              )}
            </div>
          </div>

          {period && (
            <span className="hidden shrink-0 rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground sm:inline">
              Período {period}
            </span>
          )}

          <Avatar className="size-8 shrink-0 lg:hidden">
            <AvatarFallback className="text-xs">PC</AvatarFallback>
          </Avatar>
        </header>

        <main className="flex-1 px-4 py-5 sm:px-6 sm:py-6">
          <div className="mx-auto max-w-7xl space-y-5">{children}</div>
        </main>
      </div>
    </div>
  )
}
