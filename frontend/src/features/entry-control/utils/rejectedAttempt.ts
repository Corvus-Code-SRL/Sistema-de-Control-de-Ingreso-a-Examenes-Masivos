import { rejectionLabel } from '../components/entryAttemptPresentation'
import type {
  CurrentRejectedAttempt, RejectionResponse, StudentMatch, Verification,
} from '../types/entry-control.types'

/** Veredicto que el resultado muestra tras un rechazo manual. */
export const MANUAL_REJECTION_VERDICT = 'RECHAZADO_MANUAL'

/**
 * Intento que deja un resultado negativo, listo para reportar. Null si autoriza o si no hay
 * estudiante identificado. Lo comparten el control de ingreso (HU-032) y el retraso (HU-037).
 */
export function toRejectedAttempt(verdict: Verification, roomId: number): CurrentRejectedAttempt | null {
  if (verdict.autorizado || !verdict.id_intento || !verdict.estudiante) return null
  return {
    attemptId: verdict.id_intento, student: verdict.estudiante, roomId,
    registeredAt: verdict.registrado_en, reason: verdict.motivo, reported: verdict.reportado ?? false,
  }
}

/** Intento que deja el rechazo manual de quien controla. */
export function toManualRejection(
  rejected: RejectionResponse, student: StudentMatch, roomId: number,
): CurrentRejectedAttempt {
  return {
    attemptId: rejected.id_intento, student, roomId,
    registeredAt: rejected.registrado_en, reason: rejectionLabel(rejected.motivo), reported: false,
  }
}

/** El resultado pasa a «Ingreso rechazado» con el intento registrado. */
export function withManualRejection<T extends Verification>(result: T, attempt: CurrentRejectedAttempt): T {
  return {
    ...result, autorizado: false, veredicto: MANUAL_REJECTION_VERDICT, motivo: attempt.reason,
    id_intento: attempt.attemptId, registrado_en: attempt.registeredAt, reportado: false,
  }
}

/** Marca como reportado el resultado del intento indicado. */
export function withReported<T extends Verification>(result: T | null, attemptId: number): T | null {
  return result?.id_intento === attemptId ? { ...result, reportado: true } : result
}
