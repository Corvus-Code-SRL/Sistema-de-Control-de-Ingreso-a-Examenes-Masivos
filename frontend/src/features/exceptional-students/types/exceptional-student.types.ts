/** Examen del docente. `permitido`: el examen todavía admite estudiantes excepcionales. */
export interface ExceptionalExam {
  id_examen: number
  nombre_examen: string
  /** Fecha del examen, AAAA-MM-DD. */
  fecha: string
  /** Hora de inicio, HH:MM. */
  hora_inicio: string
  estado: string
  permitido: boolean
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
  /** Carrera de su inscripción más reciente; nula si no tiene ninguna. */
  carrera: ExceptionalStudentCareer | null
  ya_en_examen: boolean
}

export interface ExceptionalRegistered {
  id_examen: number
  id_estudiante: number
  cod_sis: string
  nombre_completo: string
  carrera: ExceptionalStudentCareer | null
  motivo_excepcional: string
  fecha_registro: string
}

export interface ExceptionalRegistration {
  data: ExceptionalRegistered
  mensaje?: string
}

export interface RegisterExceptionalInput {
  studentId: number
  reason: string
}
