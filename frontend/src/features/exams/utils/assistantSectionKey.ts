import type { Exam } from '../types/exams.types';

/**
 * Key de la sección de ambientes de los auxiliares (HU-09). Cambia cuando el examen
 * cambia de estado o de ambientes: la sección se monta de nuevo y pide el listado,
 * porque tras cancelar ya no es editable y al quitar un ambiente sus auxiliares
 * quedan sin ambiente.
 */
export function assistantSectionKey(exam: Pick<Exam, 'estado' | 'ambientes'>): string {
  const classroomIds = (exam.ambientes ?? [])
    .map((classroom) => classroom.id_ambiente)
    .sort((a, b) => a - b);

  return `${exam.estado}-${classroomIds.join(',')}`;
}
