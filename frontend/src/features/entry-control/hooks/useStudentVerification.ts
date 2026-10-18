import { useEffect, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { rejectionLabel } from '../components/entryAttemptPresentation'
import { entryControlService } from '../services/entryControlService'
import type { CurrentRejectedAttempt, EntryActionInput, RejectionReason, StudentMatch, Verification, VerificationInput } from '../types/entry-control.types'
import { CI_MAX_LENGTH, digitsOnly, isValidCi, isValidSis, SIS_LENGTH } from '../utils/identificationValidation'

export function useStudentVerification(examId: number, roomId: number | null, onChange: () => void) {
  const [sis, setSis] = useState('')
  const [ci, setCi] = useState('')
  const [result, setResult] = useState<Verification | null>(null)
  const [rejectedAttempt, setRejectedAttempt] = useState<CurrentRejectedAttempt | null>(null)
  const [busy, setBusy] = useState<'verify' | 'confirm' | 'reject' | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  useEffect(() => { setResult(null); setError(null); setRejectedAttempt(null) }, [roomId])

  function reset() {
    setSis('')
    setCi('')
    setResult(null)
    setRejectedAttempt(null)
    setError(null)
  }

  function changeSis(value: string) {
    setSis(digitsOnly(value, SIS_LENGTH))
    setResult(null)
    setRejectedAttempt(null)
    setError(null)
  }

  function changeCi(value: string) {
    setCi(digitsOnly(value, CI_MAX_LENGTH))
    setResult(null)
    setRejectedAttempt(null)
    setError(null)
  }

  function choose(student: StudentMatch, field: 'sis' | 'ci') {
    if (field === 'ci') {
      setCi(digitsOnly(student.ci ?? '', CI_MAX_LENGTH))
    } else {
      setSis(digitsOnly(student.cod_sis, SIS_LENGTH))
    }
    setResult(null)
    setRejectedAttempt(null)
    setError(null)
  }

  function input(): VerificationInput | null {
    if (!roomId) {
      setError('Seleccione un ambiente de control.')
      return null
    }
    if (!sis && !ci) {
      setError('Ingrese un código SIS o un CI.')
      return null
    }
    if (sis && !isValidSis(sis)) {
      setError('El código SIS debe tener exactamente 9 dígitos.')
      return null
    }
    if (!isValidCi(ci)) {
      setError('El CI solo puede contener hasta 10 dígitos.')
      return null
    }
    return { id_ambiente: roomId, ...(sis ? { cod_sis: sis } : {}), ...(ci ? { ci } : {}) }
  }

  function applyVerdict(verdict: Verification, selectedRoomId: number) {
    setResult(verdict)
    setRejectedAttempt(!verdict.autorizado && verdict.id_intento && verdict.estudiante ? {
      attemptId: verdict.id_intento, student: verdict.estudiante, roomId: selectedRoomId,
      registeredAt: verdict.registrado_en, reason: verdict.motivo, reported: verdict.reportado ?? false,
    } : null)
    if (!verdict.autorizado && verdict.id_intento) onChange()
  }

  async function verify() {
    const payload = input()
    if (!payload) return
    setBusy('verify')
    setError(null)
    setNotice(null)
    setRejectedAttempt(null)
    try {
      applyVerdict(await entryControlService.verify(examId, payload), payload.id_ambiente)
    } catch (cause) {
      setResult(null)
      setError(cause instanceof Error ? cause.message : 'No se pudo verificar al estudiante.')
    } finally {
      setBusy(null)
    }
  }

  function actionInput(studentId: number, selectedRoomId: number): EntryActionInput {
    return { id_estudiante: studentId, id_ambiente: selectedRoomId, ...(ci ? { ci } : {}) }
  }

  async function confirm() {
    if (!result?.autorizado || !result.estudiante || !roomId) return
    setBusy('confirm')
    setError(null)
    try {
      await entryControlService.confirm(examId, actionInput(result.estudiante.id_estudiante, roomId))
      setNotice(`Ingreso registrado: ${result.estudiante.nombre_completo}.`)
      reset()
      onChange()
    } catch (cause) {
      await handleConfirmError(cause)
    } finally {
      setBusy(null)
    }
  }

  async function handleConfirmError(cause: unknown) {
    if (cause instanceof ApiError && cause.status === 409) {
      const payload = input()
      if (payload) {
        try {
          applyVerdict(await entryControlService.verify(examId, payload), payload.id_ambiente)
        } catch { /* Conserva el error original. */ }
      }
    }
    setError(cause instanceof Error ? cause.message : 'No se pudo registrar el ingreso.')
  }

  async function reject(motivo: RejectionReason, observacion: string): Promise<CurrentRejectedAttempt | null> {
    if (!result?.autorizado || !result.estudiante || !roomId) return null
    setBusy('reject')
    setError(null)
    try {
      const rejected = await entryControlService.reject(examId, {
        ...actionInput(result.estudiante.id_estudiante, roomId),
        motivo, observacion: observacion.trim() || undefined,
      })
      if (!rejected.registrado || !rejected.id_intento) {
        throw new Error('No se pudo registrar el rechazo. Verifique nuevamente al estudiante.')
      }
      const attempt: CurrentRejectedAttempt = {
        attemptId: rejected.id_intento, student: result.estudiante, roomId,
        registeredAt: rejected.registrado_en, reason: rejectionLabel(rejected.motivo), reported: false,
      }
      setRejectedAttempt(attempt)
      setResult({
        ...result, autorizado: false, veredicto: 'RECHAZADO_MANUAL', motivo: attempt.reason,
        id_intento: attempt.attemptId, registrado_en: attempt.registeredAt, reportado: false,
      })
      setNotice('Intento rechazado y registrado.')
      onChange()
      return attempt
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'No se pudo registrar el rechazo.')
      return null
    } finally {
      setBusy(null)
    }
  }

  function markReported(attemptId: number) {
    setRejectedAttempt((current) => current?.attemptId === attemptId ? { ...current, reported: true } : current)
    setResult((current) => current?.id_intento === attemptId ? { ...current, reportado: true } : current)
  }

  return { sis, ci, result, rejectedAttempt, busy, error, notice, setNotice, changeSis, changeCi, choose, verify, confirm, reject, reset, markReported }
}
