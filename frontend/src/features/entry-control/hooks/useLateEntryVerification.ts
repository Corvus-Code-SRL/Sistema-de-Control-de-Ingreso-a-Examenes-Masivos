import { useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { throttleWaitSeconds } from '@/lib/throttle'
import { LATE_ENTRY_MESSAGES } from '../components/lateEntryMessages'
import { lateEntryService } from '../services/lateEntryService'
import type { StudentMatch } from '../types/entry-control.types'
import type {
  LateEntryActionInput, LateEntryRequest, LateEntryVerification, LateEntryVerificationInput,
} from '../types/late-entry.types'
import { CI_MAX_LENGTH, digitsOnly, isValidCi, isValidSis, SIS_LENGTH } from '../utils/identificationValidation'

type Busy = 'verify' | 'request' | 'register'

/** Ante un 429 (RNF-03) pide esperar, en español, en lugar del texto del servidor. */
function messageOf(cause: unknown, fallback: string): string {
  if (cause instanceof ApiError && cause.isTooManyRequests) {
    return LATE_ENTRY_MESSAGES.throttled(throttleWaitSeconds(cause))
  }
  return cause instanceof Error && cause.message !== '' ? cause.message : fallback
}

/**
 * Verificación y escritura del ingreso con retraso. `controlRoomId` es el ambiente del auxiliar
 * (lo envía al verificar y al solicitar); el docente no tiene uno y elige el ambiente al registrar.
 * Un 409 (examen cerrado, tiempo cumplido, solicitud pendiente, ingreso duplicado u otro examen sin
 * confirmar) avisa con `onConflict` y vuelve a verificar, para que el resultado muestre el estado
 * real del estudiante.
 */
export function useLateEntryVerification(
  examId: number,
  controlRoomId: number | null,
  onConflict: () => void
) {
  const [sis, setSis] = useState('')
  const [ci, setCi] = useState('')
  const [result, setResult] = useState<LateEntryVerification | null>(null)
  const [sentRequest, setSentRequest] = useState<LateEntryRequest | null>(null)
  const [busy, setBusy] = useState<Busy | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  function clearResult() {
    setResult(null)
    setSentRequest(null)
    setError(null)
  }

  function reset() {
    setSis('')
    setCi('')
    clearResult()
  }

  function changeSis(value: string) {
    setSis(digitsOnly(value, SIS_LENGTH))
    clearResult()
  }

  function changeCi(value: string) {
    setCi(digitsOnly(value, CI_MAX_LENGTH))
    clearResult()
  }

  /** Una sugerencia completa el campo desde el que se eligió, como en el control de ingreso. */
  function choose(student: StudentMatch, field: 'sis' | 'ci') {
    if (field === 'ci') {
      setCi(digitsOnly(student.ci ?? '', CI_MAX_LENGTH))
    } else {
      setSis(digitsOnly(student.cod_sis, SIS_LENGTH))
    }
    clearResult()
  }

  function verificationInput(): LateEntryVerificationInput | null {
    if (!isValidSis(sis)) {
      setError(LATE_ENTRY_MESSAGES.sisRequired)
      return null
    }
    if (!isValidCi(ci)) {
      setError(LATE_ENTRY_MESSAGES.ciInvalid)
      return null
    }

    return { cod_sis: sis, ...(ci ? { ci } : {}), ...(controlRoomId !== null ? { id_ambiente: controlRoomId } : {}) }
  }

  async function refreshVerdict(payload: LateEntryVerificationInput) {
    try {
      setResult(await lateEntryService.verify(examId, payload))
    } catch (cause) {
      setResult(null)
      if (cause instanceof ApiError && cause.status === 409) onConflict()
    }
  }

  async function verify() {
    const payload = verificationInput()
    if (!payload) return
    setBusy('verify')
    setError(null)
    setNotice(null)
    setSentRequest(null)
    try {
      setResult(await lateEntryService.verify(examId, payload))
    } catch (cause) {
      setResult(null)
      setError(messageOf(cause, LATE_ENTRY_MESSAGES.verifyFailed))
      if (cause instanceof ApiError && cause.status === 409) onConflict()
    } finally {
      setBusy(null)
    }
  }

  function actionInput(roomId: number, confirmOtherExam: boolean): LateEntryActionInput | null {
    const student = result?.autorizado ? result.estudiante : null
    if (!student) return null

    return {
      id_estudiante: student.id_estudiante,
      id_ambiente: roomId,
      ...(ci ? { ci } : {}),
      ...(confirmOtherExam ? { confirmar_otro_examen: true } : {}),
    }
  }

  async function handleWriteError(cause: unknown) {
    setError(messageOf(cause, LATE_ENTRY_MESSAGES.actionFailed))
    if (!(cause instanceof ApiError && cause.status === 409)) return
    onConflict()
    const payload = verificationInput()
    if (payload) await refreshVerdict(payload)
  }

  /**
   * El auxiliar pide la autorización del docente desde su ambiente. `confirmOtherExam`: la envía
   * pese a la advertencia de otro examen.
   */
  async function request(confirmOtherExam = false) {
    const payload = controlRoomId === null ? null : actionInput(controlRoomId, confirmOtherExam)
    if (!payload) return
    setBusy('request')
    setError(null)
    try {
      setSentRequest(await lateEntryService.request(examId, payload))
    } catch (cause) {
      await handleWriteError(cause)
    } finally {
      setBusy(null)
    }
  }

  /** El docente registra directamente en el ambiente elegido, también pese a la advertencia si la confirmó. */
  async function register(roomId: number, confirmOtherExam = false) {
    const payload = actionInput(roomId, confirmOtherExam)
    const name = result?.estudiante?.nombre_completo ?? ''
    if (!payload) return
    setBusy('register')
    setError(null)
    try {
      const registration = await lateEntryService.register(examId, payload)
      reset()
      setNotice(LATE_ENTRY_MESSAGES.registered(name, registration.hora_ingreso))
    } catch (cause) {
      await handleWriteError(cause)
    } finally {
      setBusy(null)
    }
  }

  return {
    sis, ci, result, sentRequest, busy, error, notice,
    changeSis, changeCi, choose, verify, request, register, reset, setNotice,
  }
}
