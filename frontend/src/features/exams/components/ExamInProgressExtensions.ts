import type { ReactNode } from 'react';
import type {
  ExamInProgress,
  InProgressStudent,
} from '../types/examInProgress.types';

/**
 * Contrato de integración de HU-038-A.
 *
 * Las features de HU-037, HU-038-B, HU-042 y HU-057 proporcionan
 * sus componentes desde la composición de rutas, sin editar la página.
 * Las extensiones reciben datos ya autorizados y limitados por ambiente.
 * Cada feature decide qué acciones mostrar según `estado` y `rol_controlador`.
 */
export interface ExamInProgressExtensions {
  /** HU-037 / HU-038-B: acciones generales del examen. */
  headerActions?: (exam: ExamInProgress) => ReactNode;

  /** HU-042 / HU-057: acciones sobre una fila, con su estudiante y examen. */
  studentActions?: (
    student: InProgressStudent,
    exam: ExamInProgress
  ) => ReactNode;

  /** HU-037 / HU-042 / HU-057: pestañas, paneles y notificaciones. */
  panels?: (exam: ExamInProgress) => ReactNode;
}
