/**
 * Áreas de trabajo de SCIEM.
 *
 * Cada área tiene su propia navegación y su propio juego de rutas. No se
 * mezclan: una pantalla pertenece a un área, nunca a las dos.
 */
export type Area = 'docente' | 'administrador'

/** Orden en el que se ofrecen las áreas al elegirlas. */
export const AREAS: readonly Area[] = ['docente', 'administrador']

export const AREA_LABELS: Record<Area, string> = {
  docente: 'Docente',
  administrador: 'Administrador',
}

/** Usuario que la aplicación considera conectado. */
export interface CurrentUser {
  nombre: string
  /** Iniciales para el avatar, que es lo único que el diseño muestra. */
  iniciales: string
  area: Area
}
