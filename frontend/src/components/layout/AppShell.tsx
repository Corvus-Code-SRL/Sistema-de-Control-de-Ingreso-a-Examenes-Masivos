import { Fragment, useContext, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { Link } from 'react-router-dom'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { AppFrame } from './AppFrame'
import { ShellSlotContext } from './shellContext'

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

type HeaderProps = Pick<AppShellProps, 'mobileTitle' | 'mobileSubtitle' | 'breadcrumbs' | 'period'>

/** Lo que cada pantalla pone en la barra superior: miga (escritorio), título (móvil) y período. */
function ShellHeader({ mobileTitle, mobileSubtitle, breadcrumbs, period }: HeaderProps) {
  return (
    <>
      <div className="min-w-0 flex-1">
        <Breadcrumb className="hidden lg:block">
          <BreadcrumbList>
            {breadcrumbs.map((crumb, index) => (
              // El separador es hermano del item, no hijo: ambos son <li>.
              <Fragment key={`${crumb.label}-${index}`}>
                <BreadcrumbItem>
                  {index === breadcrumbs.length - 1 ? (
                    <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
                  ) : crumb.to ? (
                    <Link to={crumb.to} className="hover:text-foreground">
                      {crumb.label}
                    </Link>
                  ) : (
                    // Grupo sin pantalla propia: texto, no «página actual» (solo lo es la última miga).
                    <span>{crumb.label}</span>
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
        <span className="hidden shrink-0 rounded-md bg-brand-soft px-2 py-1 text-xs font-medium text-brand-deep sm:inline">
          Período {period}
        </span>
      )}
    </>
  )
}

/**
 * Contenido de una pantalla más los datos de su barra superior.
 *
 * Tiene dos modos según dónde se monte:
 * - Dentro de `AppLayout` (todas las rutas reales) el marco ya existe y no se vuelve a montar al
 *   navegar: aquí solo se proyecta la cabecera de la pantalla en el lugar que el marco reserva
 *   para ella (un portal) y se devuelve el contenido.
 * - Sin `AppLayout` (modo aislado) pinta el marco completo. Existe para probar una pantalla por
 *   separado; ninguna ruta debe depender de él, porque cada navegación volvería a montar el menú.
 */
export function AppShell({ children, ...header }: AppShellProps) {
  const slot = useContext(ShellSlotContext)
  const content = <div className="mx-auto max-w-7xl space-y-5">{children}</div>

  if (slot === undefined) {
    return (
      <AppFrame mode="standalone" header={<ShellHeader {...header} />}>
        {content}
      </AppFrame>
    )
  }

  return (
    <>
      {slot !== null && createPortal(<ShellHeader {...header} />, slot)}
      {content}
    </>
  )
}
