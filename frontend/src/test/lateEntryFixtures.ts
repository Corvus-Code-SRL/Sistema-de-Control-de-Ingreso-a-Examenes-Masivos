import type { ExamInProgress } from '@/features/exams'
import type {
  LateEntryRequest, LateEntryVerification, OtherExamWarning,
} from '@/features/entry-control/types/late-entry.types'

/** Datos de prueba del ingreso con retraso (HU-037): un examen EN_CURSO con dos ambientes. */

export function makeLateEntryRequest(overrides: Partial<LateEntryRequest> = {}): LateEntryRequest {
  return {
    id_solicitud: 31,
    estado: 'PENDIENTE',
    estudiante: { id_estudiante: 501, cod_sis: '202301903', nombre_completo: 'Lucía Fernández Rojas' },
    ambiente: { id_ambiente: 6, nro_aula: '692B' },
    solicitante: { nombre_completo: 'Mario Quispe' },
    resolutor: null,
    solicitado_en: '2026-10-31T14:12:00+00:00',
    hora_solicitud: '10:12',
    resuelto_en: null,
    otro_examen: null,
    ...overrides,
  }
}

export function makeLateEntryVerification(
  overrides: Partial<LateEntryVerification> = {}
): LateEntryVerification {
  return {
    veredicto: 'AUTORIZADO',
    motivo: 'Inscrito en un grupo del examen y asignado a este ambiente.',
    autorizado: true,
    estudiante: {
      id_estudiante: 501,
      cod_sis: '202301903',
      nombre_completo: 'Lucía Fernández Rojas',
      ci: '9342145',
      ci_pendiente: false,
      carrera: 'Ing. de Sistemas',
      foto_url: null,
    },
    auxiliar: null,
    grupo: { id_grupo: 11, num_grupo: '1' },
    ambiente_asignado: { id_ambiente: 6, nro_aula: '692B' },
    antecedentes: { total: 0, latest: null },
    ingreso_previo: null,
    solicitud_pendiente: null,
    otro_examen: null,
    ...overrides,
  }
}

export function makeExamInProgress(overrides: Partial<ExamInProgress> = {}): ExamInProgress {
  return {
    id_examen: 7,
    nombre_examen: 'Primer parcial',
    materia: 'Bases de Datos I',
    estado: 'EN_CURSO',
    rol_controlador: 'DOCENTE',
    id_ambiente_asignado: null,
    ambientes: [
      { id_ambiente: 5, nro_aula: '691A', capacidad: 40 },
      { id_ambiente: 6, nro_aula: '692B', capacidad: 40 },
    ],
    inicio_real_en: '2026-10-31T14:00:00+00:00',
    fin_real_en: null,
    inicio_automatico: false,
    fin_automatico: false,
    fin_programado_en: '2026-10-31T16:00:00+00:00',
    hora_servidor_en: '2026-10-31T14:10:00+00:00',
    totales: { a_tiempo: 1, con_retraso: 0, ausentes: 1, presentes: 1, total: 2, incidencias_pendientes: 0 },
    estudiantes: [],
    ...overrides,
  }
}

export function makeOtherExamWarning(overrides: Partial<OtherExamWarning> = {}): OtherExamWarning {
  return {
    id_examen: 9,
    nombre_examen: 'Parcial de Física',
    materia: 'Física General',
    docente: 'Ana Rojas',
    hora_ingreso: '08:05',
    nro_aula: '691A',
    ...overrides,
  }
}
