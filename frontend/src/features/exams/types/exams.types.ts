export type ExamCategory = 'REGULAR' | 'FINAL' | 'MESA' | 'ADMISION';

export interface Subject {
  id_materia: number;
  nombre: string;
  codigo: string;
}

export interface Classroom {
  id_ambiente: number;
  nro_aula: string;
  capacidad: number;
  ubicacion?: string;
}

export interface Group {
  id_grupo: number;
  num_grupo: number;
  gestion: string;
  estado: string;
  id_materia?: number;
  cantidad_estudiantes?: number;
  tiene_nomina?: boolean;
  inscritos_count?: number;
}

export interface AssignGroupsPayload {
  grupos: number[];
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
  normas?: string;
  id_tipo_examen: number;
  tipo_examen?: ExamType;
  ambientes?: Classroom[];
  grupos?: Group[];
  estado?: string;
  materia?: Subject;
}

export interface ExamFormOptions {
  materias: Subject[];
  ambientes: Classroom[];
  grupos: Group[];
}

export interface CreateExamFormData {
  nombre_examen: string;
  id_materia: number | null;
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
  id_materia: number;
  categoria: ExamCategory;
  fecha: string;
  hora_inicio: string;
  duracion: number;
  ambientes: number[];
  grupos?: number[];
  normas?: string;
}

export type CreateExamPayload = CreateExamDto;

export interface StepValidationResult {
  isValid: boolean;
  errors: Record<string, string>;
  warnings: Record<string, string>;
}

export type StepStatus = 'pending' | 'active' | 'completed' | 'error';

export interface ScheduledExamItem {
  id: number;
  nombre: string;
  materia: string;
  id_materia?: number;
  grupoStr: string;
  fechaISO: string;
  dayNum: string;
  daySubtitle: string;
  horario: string;
  estadoBadge: {
    label: string;
    type: 'warn' | 'info' | 'ok' | 'neutral';
  };
  aulas: string;
  habilitadosCount: number;
  auxiliaresStr: string;
  canEdit?: boolean;
}

export interface DayGroupedExams {
  dayNum: string;
  daySubtitle: string;
  exams: ScheduledExamItem[];
}
