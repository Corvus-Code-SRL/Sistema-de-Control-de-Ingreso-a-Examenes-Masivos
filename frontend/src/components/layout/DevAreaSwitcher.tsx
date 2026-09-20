import { FlaskConical } from 'lucide-react'
import { AREAS, AREA_LABELS, useCurrentUser, type Area } from '@/features/auth'

/**
 * APAÑO DE DESARROLLO — se elimina cuando exista autenticación real.
 *
 * Permite ver las pantallas de cada área sin login. No es un control de acceso:
 * solo decide qué navegación y qué rutas dibuja el frontend. El backend sigue
 * resolviendo su usuario fijo desde configuración y no se entera de esto.
 *
 * Para quitarlo: borrar este archivo y su uso en `AppSidebar`.
 */
export function DevAreaSwitcher() {
  const { area, setArea } = useCurrentUser()
  const selectId = 'dev-area-switcher'

  return (
    <div className="border-t border-sidebar-border px-4 py-3">
      <label
        htmlFor={selectId}
        className="sciem-overline flex items-center gap-1.5 pb-1.5 text-sb-group"
      >
        <FlaskConical className="size-3 shrink-0" aria-hidden="true" />
        Vista de desarrollo
      </label>

      <select
        id={selectId}
        value={area}
        onChange={(event) => setArea(event.target.value as Area)}
        className="h-9 w-full rounded-md border border-sidebar-border bg-sb-hover px-2 text-sm font-medium text-sidebar-foreground outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
      >
        {AREAS.map((opcion) => (
          <option key={opcion} value={opcion}>
            {AREA_LABELS[opcion]}
          </option>
        ))}
      </select>
    </div>
  )
}
