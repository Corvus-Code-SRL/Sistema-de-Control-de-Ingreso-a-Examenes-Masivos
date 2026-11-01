/** Fila del reporte: un estudiante agrupado con su conteo de intentos. */
export interface AlienAttemptsStudent {
  id_estudiante: number
  nombre_completo: string
  cod_sis: string
  intentos: number
  examenes: string[]
  /** id del registro en la Central de Riesgo; null si el estudiante no está en ella. */
  id_cr_estudiante: number | null
}

/** Filtros del reporte; los tres viven en la URL (`?desde=&hasta=&facultad=&page=`). */
export interface AlienAttemptsFilters {
  desde: string
  hasta: string
  facultad: number | null
  page: number
}

/** Totales del reporte (meta del backend). */
export interface AlienAttemptsStats {
  intentos: number
  estudiantes: number
  enCentralRiesgo: number
}

/** Página del reporte ya normalizada para la vista. */
export interface AlienAttemptsPage {
  items: AlienAttemptsStudent[]
  stats: AlienAttemptsStats
  page: number
  perPage: number
  total: number
  totalPages: number
}

/** Respuesta cruda del backend (AlienAttemptsReportResource + meta). */
export interface AlienAttemptsListResponse {
  data: AlienAttemptsStudent[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
    total_intentos: number
    total_estudiantes: number
    total_en_central_riesgo: number
  }
}

/** Facultad activa del catálogo, para el filtro. */
export interface Faculty {
  id_facultad: number
  nombre: string
  codigo: string
}

/** Respuesta cruda de GET /api/facultades. */
export interface FacultyListResponse {
  data: Faculty[]
}
