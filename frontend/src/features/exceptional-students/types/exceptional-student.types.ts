/** Examen En curso del docente responsable, en el que se puede registrar un estudiante excepcional. */
export interface ExceptionalExam {
  id_examen: number
  nombre_examen: string
  /** Fecha del examen, AAAA-MM-DD. */
  fecha: string
  /** Hora de inicio, HH:MM. */
  hora_inicio: string
  estado: string
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

export interface ExceptionalOptions {
  grupos: { id_grupo: number; nombre: string }[]
  /** Carreras que ofrece cada grupo del examen. */
  carreras: { id_grupo: number; id_carrera: number; nombre: string }[]
  ambientes: { id_ambiente: number; nombre: string; capacidad: number; ocupados: number }[]
}

export interface RegisterExceptionalInput {
  studentId: number
  reason: string
  groupId: number
  careerId: number
  roomId: number
}
