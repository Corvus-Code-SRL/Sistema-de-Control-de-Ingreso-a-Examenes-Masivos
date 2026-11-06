import { useLayoutEffect, useRef, useState } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '@/features/auth'
import { AppFrame } from './AppFrame'
import { ShellSlotContext } from './shellContext'

/**
 * Marco persistente de un área (Docente, Auxiliar o Administrador): se monta una vez y las pantallas
 * se dibujan dentro de `<Outlet />`. Navegar entre pantallas conserva el menú, su desplazamiento y
 * sus secciones abiertas; solo cambia el contenido y la cabecera que cada `AppShell` proyecta.
 *
 * Sin sesión (anónima o expirada) no pinta el marco: `ProtectedRoute` redirige al login y el menú
 * no debe verse ni un instante.
 */
export function AppLayout() {
  const { estado } = useAuth()
  const { pathname } = useLocation()
  const [slot, setSlot] = useState<HTMLElement | null>(null)
  const mainRef = useRef<HTMLElement>(null)
  const previousPathname = useRef(pathname)

  // Al cambiar de pantalla (no en el primer montaje) el contenido vuelve arriba y el foco pasa al
  // contenido, para que el teclado y los lectores de pantalla no se queden en el enlace del menú.
  useLayoutEffect(() => {
    if (previousPathname.current === pathname) return

    previousPathname.current = pathname
    window.scrollTo({ top: 0 })

    const main = mainRef.current

    // Si la pantalla nueva ya enfocó algo propio (un campo con autofocus), se respeta.
    if (main !== null && !main.contains(document.activeElement)) {
      main.focus({ preventScroll: true })
    }
  }, [pathname])

  if (estado === 'anonimo' || estado === 'expirada') {
    return (
      <ShellSlotContext.Provider value={null}>
        <Outlet />
      </ShellSlotContext.Provider>
    )
  }

  return (
    <ShellSlotContext.Provider value={slot}>
      <AppFrame
        mode="layout"
        header={<div ref={setSlot} className="contents" />}
        mainRef={mainRef}
      >
        <Outlet />
      </AppFrame>
    </ShellSlotContext.Provider>
  )
}
