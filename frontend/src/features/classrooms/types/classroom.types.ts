export interface Classroom {
  id_ambiente: number
  nro_aula: string
  capacidad: number
  ubicacion: string
  activo: boolean
}

export interface ClassroomFormState {
  nro_aula: string
  capacidad: string
  ubicacion: string
}

export interface ClassroomListResponse {
  data: Classroom[]
}

export interface ClassroomResponse {
  data: Classroom
}

export interface ClassroomValidationErrors {
  nro_aula?: string[]
  capacidad?: string[]
  ubicacion?: string[]
}
