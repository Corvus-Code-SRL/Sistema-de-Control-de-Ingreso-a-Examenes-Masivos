import { createContext } from 'react'

/**
 * Lugar de la barra superior donde las páginas proyectan sus migas y su período.
 *
 * - `undefined`: no hay `AppLayout` encima; `AppShell` pinta el marco completo (modo aislado).
 * - `null`: hay `AppLayout`, pero aún no existe el lugar (primer render o sesión sin marco).
 * - `HTMLElement`: el lugar donde `AppShell` proyecta la cabecera con un portal.
 */
export const ShellSlotContext = createContext<HTMLElement | null | undefined>(undefined)
