/** Carrera que atiende un grupo; `principal` es la del propio grupo. */
export interface ExceptionalCareer {
  id_carrera: number
  nombre: string
  principal: boolean
}

/** Grupo del docente en el período vigente. `bloqueado`: hay un examen en ingreso o en curso. */
export interface ExceptionalGroup {
  id_grupo: number
  num_grupo: string
  label: string
  bloqueado: boolean
  carreras: ExceptionalCareer[]
}

export interface ExceptionalStudentCareer {
  id_carrera: number
  nombre: string
}

/** Estudiante encontrado por código SIS, antes de registrarlo. */
export interface ExceptionalCandidate {
  id_estudiante: number
  cod_sis: string
  nombre_completo: string
  ci: string | null
  /** Carrera de su inscripción más reciente en otro grupo; nula si no tiene ninguna. */
  carrera: ExceptionalStudentCareer | null
  ya_inscrito: boolean
}

export interface ExceptionalRegistered {
  id_grupo: number
  id_estudiante: number
  cod_sis: string
  nombre_completo: string
  carrera: ExceptionalStudentCareer | null
  es_excepcional: boolean
  motivo_excepcional: string
  fecha_inscripcion: string
}

export interface ExceptionalRegistration {
  data: ExceptionalRegistered
  mensaje?: string
}

export interface RegisterExceptionalInput {
  studentId: number
  reason: string
  /** Solo hace falta cuando el grupo atiende más de una carrera. */
  careerId?: number
}
