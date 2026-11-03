import { useLocation } from 'react-router-dom'

/** Destino del detalle de una operación: la misma pantalla con `?detalle=<id>`, conservando los filtros. */
export function useDetailLink() {
  const { pathname, search } = useLocation()

  return (id: number) => {
    const params = new URLSearchParams(search)
    params.set('detalle', String(id))

    return { pathname, search: `?${params.toString()}` }
  }
}
