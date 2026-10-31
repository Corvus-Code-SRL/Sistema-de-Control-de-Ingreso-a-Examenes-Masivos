import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

/**
 * Marco de la pantalla de acceso (docs/design/rnf-02/17-login.html, L.1 y L.13).
 *
 * - Desde 768 px: panel de marca de ancho fijo a la izquierda sobre el color profundo, y la tarjeta
 *   del formulario centrada a la derecha. La tarjeta usa `max-width`, nunca un ancho fijo.
 * - Por debajo de 768 px el panel pasa a ser una barra superior y el formulario ocupa todo el ancho.
 *
 * Un solo juego de puntos de corte sirve al tamaño de ventana y al zoom: con el zoom al 200 % una
 * pantalla de 1440 px tiene un viewport efectivo de 720 px y cae, correctamente, en la versión móvil.
 */
export function LoginLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-dvh flex-col bg-background md:flex-row">
      <BrandPane />

      <main className="flex flex-1 flex-col md:items-center md:justify-center md:p-10">
        <div
          className={cn(
            'flex w-full flex-1 flex-col gap-5 px-4 pt-6 pb-0',
            'md:max-w-[400px] md:flex-none md:gap-6 md:rounded-xl md:border md:border-border-soft',
            'md:bg-surface md:p-9 md:shadow-sm xl:max-w-[440px]'
          )}
        >
          {children}
        </div>
      </main>
    </div>
  )
}

function BrandPane() {
  return (
    <aside
      className={cn(
        'flex flex-none flex-col bg-brand-deep px-5 pt-7 pb-6 text-sidebar-foreground',
        'md:min-h-dvh md:w-[360px] md:justify-between md:p-9 xl:w-[560px] xl:p-14'
      )}
    >
      <div className="flex items-center gap-3.5">
        <span
          aria-hidden="true"
          className="font-brand flex size-12 flex-none items-center justify-center rounded-xl bg-brand text-[22px] font-bold text-white"
        >
          S
        </span>
        <div>
          <p className="font-brand text-[22px] leading-[1.1] font-bold tracking-[.02em] text-white">SCIEM</p>
          <p className="mt-[3px] text-[13px] leading-[1.3] text-sb-sub">Control de Ingreso a Exámenes</p>
        </div>
      </div>

      <div className="hidden flex-col gap-3.5 md:flex">
        <p className="font-brand text-[26px] leading-[34px] font-semibold tracking-[-.01em] text-white xl:text-[32px] xl:leading-10">
          Control de ingreso a exámenes masivos
        </p>
        <p className="text-[15px] leading-6 text-sb-sub">
          Universidad Mayor de San Simón · Facultad de Ciencias y Tecnología
        </p>
      </div>

      <p className="hidden text-xs text-sb-group md:block">Corvus Code S.R.L. · 2026</p>
    </aside>
  )
}
