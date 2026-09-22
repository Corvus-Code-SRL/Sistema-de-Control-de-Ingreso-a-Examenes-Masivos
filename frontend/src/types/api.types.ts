/**
 * Formas de respuesta comunes a toda la API.
 *
 * Los módulos del backend devuelven siempre `data` y, según el caso, `meta`.
 * Los errores de Laravel llegan como `message` y, en los 422, `errors`.
 */

/** Colección con metadatos, como la del catálogo de materias. */
export interface CollectionResponse<TData, TMeta> {
  data: TData[]
  meta: TMeta
  mensaje?: string | null
}

/** Recurso único con metadatos, como el detalle de un grupo. */
export interface ResourceResponse<TData, TMeta> {
  data: TData
  meta: TMeta
}

/** Cuerpo de error de Laravel. En los 422 `errors` trae los campos rechazados. */
export interface ApiErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

/**
 * Página solicitada al servidor.
 *
 * El catálogo puede tener cientos de materias por facultad, así que se pide
 * siempre por página y nunca se descarga entero.
 */
export interface PageRequest {
  page: number
  perPage: number
}

/** Página devuelta: los elementos más lo necesario para dibujar el paginador. */
export interface Page<TItem> {
  items: TItem[]
  page: number
  perPage: number
  total: number
  totalPages: number
}
