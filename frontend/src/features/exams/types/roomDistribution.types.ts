export interface DistributionExamOption {
  id_examen: number
  nombre: string
  materia: { nombre: string } | null
  fecha: string
  hora_inicio: string
  estado: 'PROGRAMADO' | 'EN_CURSO'
  cantidad_ambientes: number
  es_creador: boolean
  editable: boolean
  distribucion_suspendida: boolean
  puede_dejar_de_usar: boolean
}

export interface DistributionRoom {
  id_ambiente: number
  nro_aula: string
  capacidad: number
  asignados: number
}

export interface DistributionStudent {
  id_estudiante: number
  codigo_sis: string
  nombre_completo: string
  id_ambiente: number | null
}

export interface RoomDistributionView {
  examen: DistributionExamOption
  editable: boolean
  motivo_solo_lectura: string | null
  version: string
  total_habilitados: number
  total_no_habilitados: number
  capacidad_total: number
  faltan_asientos: number
  ambientes: DistributionRoom[]
  estudiantes: DistributionStudent[]
}

export interface DistributionProposal {
  estudiantes: DistributionStudent[]
  sin_asignar: number
}

export interface DistributionAssignment {
  id_estudiante: number
  id_ambiente: number
}

export interface SaveDistributionPayload {
  version: string
  asignaciones: DistributionAssignment[]
}

export type DistributionAssignments = ReadonlyMap<number, number | null>
export type DistributionConfirmation = 'leave' | 'discard' | 'overwrite' | 'reload' | 'stop'
