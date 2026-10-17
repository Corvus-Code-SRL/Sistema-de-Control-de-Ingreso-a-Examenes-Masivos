import { useLocation } from 'react-router-dom'

/** Destino del detalle de un registro: conserva la búsqueda, el tipo y la página de la URL actual. */
export function useDetailLink() {
  const { search } = useLocation()

  return (id: number) => ({ pathname: `/central-riesgo/${id}`, search })
}
