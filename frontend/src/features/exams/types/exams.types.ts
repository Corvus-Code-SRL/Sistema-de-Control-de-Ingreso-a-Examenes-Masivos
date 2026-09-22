/** Valores de public.categoria_examen. */
export type ExamCategory = 'REGULAR' | 'MESA' | 'ADMISION';

/** Valores de public.estado_examen. */
export type ExamStatus = 'PROGRAMADO' | 'EN_INGRESO' | 'EN_CURSO' | 'FINALIZADO' | 'CANCELADO';

/**
 * Par materia-carrera seleccionable. La misma materia puede estar en varias
 * carreras, por eso la opción siempre lleva la carrera.
 */
export interface SubjectCareerOption {
  id_carrera: number;
  id_materia: number;
  nombre: string;
  codigo: string;
  carrera: string;
  es_mia: boolean;
}

export interface Classroom {
  id_ambiente: number;
  nro_aula: string;
  capacidad: number;
}

export interface Group {
  id_grupo: number;
  num_grupo: number;
  gestion: string;
  estado: string;
  id_carrera?: number;
  id_materia?: number;
  tiene_nomina?: boolean;
  inscritos_count?: number;
}

export interface ExamType {
  id_tipo_examen: number;
  nombre: string;
  categoria: ExamCategory;
}

export interface Exam {
  id_examen: number;
  nombre_examen: string;
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  duracion: number;
  normas: string | null;
  estado: ExamStatus;
  id_tipo_examen: number;
  id_carrera: number;
  id_materia: number;
  id_usuario_docente: string;
  tipo_examen?: ExamType;
  materia?: { id_materia: number; nombre: string; codigo: string };
  carrera?: { id_carrera: number; nombre: string };
  ambientes?: Classroom[];
}

export interface ExamFormOptions {
  materias: SubjectCareerOption[];
  ambientes: Classroom[];
  grupos: Group[];
}

/** El par se elige como una sola opción: nunca materia sin carrera. */
export interface SubjectCareerKey {
  id_carrera: number;
  id_materia: number;
}

export interface CreateExamFormData {
  nombre_examen: string;
  materia: SubjectCareerKey | null;
  categoria: ExamCategory;
  fecha: string;
  hora_inicio: string;
  duracion: number;
  ambientes: number[];
  grupos: number[];
  normas: string;
}

export interface CreateExamDto {
  nombre_examen: string;
  id_carrera: number;
  id_materia: number;
  categoria: ExamCategory;
  fecha: string;
  hora_inicio: string;
  duracion: number;
  ambientes: number[];
  normas?: string;
  /** Reenvío tras revisar nombre duplicado o superposición de horario/ambiente. */
  confirmar_advertencias?: boolean;
}

export type CreateExamPayload = CreateExamDto;

export interface StepValidationResult {
  isValid: boolean;
  errors: Record<string, string>;
  warnings: Record<string, string>;
}
