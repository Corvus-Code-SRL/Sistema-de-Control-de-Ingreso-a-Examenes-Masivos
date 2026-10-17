import { useEffect, useState } from 'react'
import { rejectionLabel } from '../components/entryAttemptPresentation'
import { ApiError } from '@/lib/api-client'
import { entryControlService } from '../services/entryControlService'
import type { CurrentRejectedAttempt, EntryActionInput, RejectionReason, StudentMatch, Verification, VerificationInput } from '../types/entry-control.types'

export function useStudentVerification(examId: number, roomId: number | null, onChange: () => void) {
  const [rejectedAttempt, setRejectedAttempt] = useState<CurrentRejectedAttempt | null>(null)
  const [sis, setSis] = useState('')
  const [ci, setCi] = useState('')
  const [result, setResult] = useState<Verification | null>(null)
  const [busy, setBusy] = useState<'verify' | 'confirm' | 'reject' | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  useEffect(() => { setResult(null); setError(null); setRejectedAttempt(null) }, [roomId])

  function reset() {
    setRejectedAttempt(null)
    setSis('')
    setCi('')
    setResult(null)
    setError(null)
  }

  function changeSis(value: string) {
    setRejectedAttempt(null)
    setSis(value.trim())
    setResult(null)
    setError(null)
  }

  function changeCi(value: string) {
    setRejectedAttempt(null)
    setCi(value.trim())
    setResult(null)
    setError(null)
  }

  function choose(student: StudentMatch) {
    setRejectedAttempt(null)
    setSis(student.cod_sis)
    setResult(null)
    setError(null)
  }

  function input(): VerificationInput | null {
    if (!roomId || !sis) {
      setError('Seleccione un ambiente e ingrese el código SIS.')
      return null
    }
    return { cod_sis: sis, id_ambiente: roomId, ...(ci ? { ci } : {}) }
  }

  async function verify() {
    const payload = input()
    if (!payload) return
    setRejectedAttempt(null)
    setBusy('verify')
    setError(null)
    setNotice(null)
    try {
      const verdict = await entryControlService.verify(examId, payload)
      setResult(verdict)
      if (!verdict.autorizado && verdict.id_intento && verdict.estudiante) {
        setRejectedAttempt({
          attemptId: verdict.id_intento, student: verdict.estudiante, registeredAt: verdict.registrado_en,
          roomId: payload.id_ambiente, reason: verdict.motivo, reported: verdict.reportado ?? false,
        })
      }
      if (!verdict.autorizado) onChange()
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
        try { setResult(await entryControlService.verify(examId, payload)) } catch { /* Conserva el error original. */ }
      }
    }
    setError(cause instanceof Error ? cause.message : 'No se pudo registrar el ingreso.')
  }

  async function reject(motivo: RejectionReason, observacion: string) {
    if (!result?.autorizado || !result.estudiante || !roomId) return false
    setBusy('reject')
    setError(null)
    try {
      const rejection = await entryControlService.reject(examId, {
        ...actionInput(result.estudiante.id_estudiante, roomId),
        motivo, observacion: observacion.trim() || undefined,
      })
      const student = result.estudiante
      setNotice('Intento rechazado y registrado.')
      setResult({ ...result, autorizado: false, veredicto: 'RECHAZADO_MANUAL', motivo: rejectionLabel(rejection.motivo) })
      if (rejection.registrado && rejection.id_intento) {
        setRejectedAttempt({
          attemptId: rejection.id_intento, student, roomId, registeredAt: rejection.registrado_en,
          reason: rejectionLabel(rejection.motivo), reported: false,
        })
      }
      onChange()
      return true
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'No se pudo registrar el rechazo.')
      return false
    } finally {
      setBusy(null)
    }
  }

  function markReported(attemptId: number) {
    setRejectedAttempt((current) => current?.attemptId === attemptId ? { ...current, reported: true } : current)
  }

  return { rejectedAttempt, markReported, sis, ci, result, busy, error, notice, setNotice, changeSis, changeCi, choose, verify, confirm, reject, reset }
}