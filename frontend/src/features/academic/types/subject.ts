export interface Career {
  id_carrera: number
  nombre: string
  codigo: string
  id_facultad: number
}

export interface SubjectCareer {
  id_materia: number
  id_carrera: number
  nombre: string
  codigo: string
  descripcion: string | null
  nivel_semestre: string | number | null
  obligatoria: boolean | null
  activa: boolean
  es_mia: boolean
  cantidad_grupos: number
  carrera: Career
}

export interface Period {
  id_periodo: number
  nombre_periodo: string
  gestion: number
}

export interface Group {
  id_grupo: number
  num_grupo: string | number
  gestion: string | number
  activo: boolean
  es_mio: boolean
  periodo: Period
}

export interface SubjectListMeta {
  total: number
  total_mias: number
  id_periodo_activo: number
}

export interface SubjectListResponse {
  data: SubjectCareer[]
  meta: SubjectListMeta
  mensaje?: string | null
}

export interface SubjectGroupsMeta {
  total: number
  total_mios: number
  id_periodo_activo: number
}

export interface SubjectGroupsResponse {
  data: {
    materia: SubjectCareer
    grupos: Group[]
  }
  meta: SubjectGroupsMeta
}
