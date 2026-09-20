import { createContext, useCallback, useMemo, useState, type ReactNode } from 'react'
import { AREA_LABELS, type Area, type CurrentUser } from '../types/auth.types'

/**
 * SUSTITUTO TEMPORAL DE LA AUTENTICACIÓN — Sprint 1.
 *
 * No hay login, ni sesión, ni token: el área se elige a mano desde el sidebar
 * para poder ver las pantallas de cada rol durante el desarrollo. Esto NO es un
 * control de acceso. El backend sigue resolviendo su usuario fijo desde
 * `config/sciem.php`, e ignora por completo lo que se elija aquí.
 *
 * Cuando llegue la autenticación real, se reemplaza la implementación de este
 * archivo por la sesión de verdad y se borra `DevAreaSwitcher`. Ni el layout ni
 * el router se tocan: ambos consumen `useCurrentUser()`, que no cambia.
 */

const STORAGE_KEY = 'sciem.dev.area'

/**
 * Personas de prueba, una por área. Nombres distintos a propósito: es la señal
 * más rápida de que el cambio de área surtió efecto.
 */
const USUARIOS: Record<Area, CurrentUser> = {
  docente: { nombre: 'P. Careaga', iniciales: 'PC', area: 'docente' },
  administrador: { nombre: 'R. Salazar', iniciales: 'RS', area: 'administrador' },
}

const AREA_POR_DEFECTO: Area = 'docente'

function esArea(valor: string | null): valor is Area {
  return valor !== null && valor in AREA_LABELS
}

/** El área sobrevive a una recarga; sin almacenamiento se vuelve a Docente. */
function leerAreaGuardada(): Area {
  try {
    const guardada = window.localStorage.getItem(STORAGE_KEY)
    return esArea(guardada) ? guardada : AREA_POR_DEFECTO
  } catch {
    return AREA_POR_DEFECTO
  }
}

export interface CurrentUserContextValue {
  user: CurrentUser
  area: Area
  setArea: (area: Area) => void
}

/**
 * El valor por defecto deja montar el sidebar fuera del proveedor, que es lo
 * que hacen las pruebas de las páginas.
 */
export const CurrentUserContext = createContext<CurrentUserContextValue>({
  user: USUARIOS[AREA_POR_DEFECTO],
  area: AREA_POR_DEFECTO,
  setArea: () => {},
})

export function CurrentUserProvider({ children }: { children: ReactNode }) {
  const [area, setAreaState] = useState<Area>(leerAreaGuardada)

  const setArea = useCallback((siguiente: Area) => {
    setAreaState(siguiente)

    try {
      window.localStorage.setItem(STORAGE_KEY, siguiente)
    } catch {
      // Sin almacenamiento el área simplemente no sobrevive a la recarga.
    }
  }, [])

  const value = useMemo(
    () => ({ user: USUARIOS[area], area, setArea }),
    [area, setArea]
  )

  return <CurrentUserContext.Provider value={value}>{children}</CurrentUserContext.Provider>
}
