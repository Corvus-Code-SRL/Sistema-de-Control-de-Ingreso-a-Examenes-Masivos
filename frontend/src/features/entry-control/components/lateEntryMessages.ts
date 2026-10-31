import type { VerificationActions } from '../types/entry-control.types'
import type { LateEntryRequestStatus } from '../types/late-entry.types'

/** Textos del ingreso con retraso (HU-037). */
export const LATE_ENTRY_MESSAGES = {
  title: 'Registrar estudiante con retraso',
  examsTitle: 'Ingreso con retraso',
  examsSubtitle: 'Exámenes en curso en los que puede registrar estudiantes con retraso.',
  emptyTitle: 'No tiene exámenes en curso',
  emptyDescription: 'El ingreso con retraso está disponible mientras un examen está en curso.',
  loadingExams: 'Cargando exámenes en curso',
  loadingExam: 'Cargando examen',
  invalidExam: 'Examen no válido.',
  missingDate: 'Fecha no disponible',
  roomsLabel: 'Ambientes',
  openExam: 'Registrar con retraso',
  backToInProgress: 'Volver al examen en curso',
  refresh: 'Actualizar',
  closedTitle: 'El examen no está en curso',
  closedDescription: 'Solo puede registrar ingresos con retraso mientras el examen está en curso. Las solicitudes quedan como consulta.',
  noRoom: 'No tiene un ambiente de control asignado. Solicite al docente que asigne uno.',
  sisRequired: 'Ingrese el código SIS de 9 dígitos.',
  ciInvalid: 'El CI solo puede contener hasta 10 dígitos.',
  verifyFailed: 'No se pudo verificar al estudiante.',
  actionFailed: 'No se pudo completar la operación. Intente de nuevo.',
  roomField: 'Ambiente de ingreso',
  roomPlaceholder: 'Seleccione un ambiente',
  closeNotice: 'Cerrar aviso',
  nextStudent: 'Siguiente estudiante',
  pendingInline: 'Este estudiante tiene una solicitud pendiente. Autorícela o rechácela.',
  requestedBy: (name: string) => `solicitado por ${name}`,
  registered: (name: string, time: string) => `Ingreso con retraso registrado: ${name} a las ${time}.`,
  approve: 'Autorizar',
  reject: 'Rechazar',
  approved: 'Ingreso autorizado',
  rejected: 'Solicitud rechazada',
  waiting: 'Esperando al docente',
  waitingDetail: (time: string) => `Solicitud enviada a las ${time}. No deje ingresar todavía.`,
  waitingApproved: 'Ingreso autorizado',
  waitingRejected: 'Solicitud rechazada por el docente',
  noticeTitle: 'Solicitud de ingreso con retraso',
  newRequest: (name: string, room: string) => `Solicitud de ingreso con retraso: ${name} · ${room}`,
  listTitle: 'Solicitudes de ingreso con retraso',
  listTitleOwn: 'Sus solicitudes',
  listEmpty: 'Todavía no hay solicitudes.',
  listLoading: 'Cargando solicitudes',
  listStale: 'Solicitudes sin actualizar. Se reintentará automáticamente.',
  columns: {
    student: 'Estudiante',
    room: 'Ambiente',
    time: 'Hora',
    requester: 'Solicitado por',
    status: 'Estado',
    actions: 'Acciones',
  },
} as const

export const LATE_ENTRY_STATUS_LABELS: Record<LateEntryRequestStatus, string> = {
  PENDIENTE: 'Pendiente',
  AUTORIZADA: 'Autorizada',
  RECHAZADA: 'Rechazada',
}

/** Acciones del resultado de verificación para el docente, que registra directamente. */
export const TEACHER_LATE_ENTRY_ACTIONS: VerificationActions = {
  confirmLabel: 'Registrar con retraso',
  busyLabel: 'Registrando…',
  rejectLabel: 'Cancelar',
  recordsAttempt: false,
}

/** Acciones del resultado de verificación para el auxiliar, que solicita la autorización. */
export const ASSISTANT_LATE_ENTRY_ACTIONS: VerificationActions = {
  confirmLabel: 'Enviar solicitud',
  busyLabel: 'Enviando…',
  rejectLabel: 'Cancelar',
  recordsAttempt: false,
}
